<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\District;
use App\Models\SafeZone;
use Illuminate\Support\Collection;

/**
 * Construye el bloque de datos verificables de RESGUARDA que se entrega al
 * modelo de IA. Las coordenadas exactas del usuario nunca se incluyen: solo el
 * distrito detectado y las distancias aproximadas a los lugares registrados.
 */
class AssistantContextBuilder
{
    private const RISK_LABELS = [
        'safe' => 'Seguro',
        'caution' => 'Precaución',
        'risk' => 'En riesgo',
        'high_risk' => 'Riesgo alto',
        'critical' => 'Riesgo crítico',
        'clearing' => 'En despeje',
    ];

    private const SEVERITY_LABELS = [
        'info' => 'informativa',
        'caution' => 'precaución',
        'danger' => 'peligro',
        'critical' => 'crítica',
    ];

    public function __construct(private DistrictLocator $locator) {}

    /**
     * @return array{context: string, district: District|null, location_used: bool}
     */
    public function build(?float $latitude, ?float $longitude, ?int $districtId): array
    {
        $lines = ['DATOS DE RESGUARDA (consultados el '.now()->format('d/m/Y H:i').')'];
        $district = $districtId !== null ? District::query()->find($districtId) : null;
        $locationUsed = false;
        $nearestZones = collect();

        if ($latitude !== null && $longitude !== null) {
            $locationUsed = true;
            $location = $this->locator->locate($latitude, $longitude);
            $district ??= $location['district'];

            $lines[] = match ($location['method']) {
                'boundary' => 'Ubicación del usuario: dentro del distrito '.$location['district']?->name.' (verificado con límites distritales).',
                'nearest_centroid' => 'Ubicación del usuario: distrito aproximado '.$location['district']?->name.' (estimado por cercanía al centro del distrito; puede no ser exacto en zonas limítrofes).',
                default => 'Ubicación del usuario: fuera de la provincia de Trujillo o no reconocida. No hay datos locales verificados para ese punto.',
            };

            $nearestZones = $this->locator->nearestSafeZones($latitude, $longitude, 5);
        } elseif ($district !== null) {
            $lines[] = 'El usuario indicó manualmente el distrito '.$district->name.'. No se conoce su posición exacta.';
        } else {
            $lines[] = 'No se conoce la ubicación del usuario ni su distrito.';
        }

        $lines[] = '';
        $lines[] = 'Distritos de la provincia de Trujillo y nivel de riesgo registrado:';
        foreach (District::query()->orderBy('name')->get(['id', 'name', 'risk_level']) as $item) {
            $lines[] = '- '.$item->name.': '.self::RISK_LABELS[$item->risk_level];
        }

        $lines[] = '';
        $lines = [...$lines, ...$this->safeZoneLines($nearestZones, $district)];

        $lines[] = '';
        $lines = [...$lines, ...$this->alertLines($district)];

        return [
            'context' => implode("\n", $lines),
            'district' => $district,
            'location_used' => $locationUsed,
        ];
    }

    /**
     * @param  Collection<int, array{zone: SafeZone, distance_km: float}>  $nearestZones
     * @return list<string>
     */
    private function safeZoneLines(Collection $nearestZones, ?District $district): array
    {
        if ($nearestZones->isNotEmpty()) {
            $lines = ['Zonas seguras registradas más cercanas al usuario (distancia en línea recta, no por calles):'];
            foreach ($nearestZones as $item) {
                $lines[] = '- '.$this->describeZone($item['zone']).'; a '.number_format($item['distance_km'], 1).' km';
            }

            return $lines;
        }

        $zones = SafeZone::query()
            ->with('district:id,name')
            ->when($district, fn ($query) => $query->where('district_id', $district->id))
            ->orderBy('name')
            ->limit(15)
            ->get();

        if ($zones->isEmpty()) {
            return [$district !== null
                ? 'No hay zonas seguras registradas en RESGUARDA para el distrito '.$district->name.'.'
                : 'No hay zonas seguras registradas en RESGUARDA.'];
        }

        $lines = [$district !== null
            ? 'Zonas seguras registradas en el distrito '.$district->name.':'
            : 'Zonas seguras registradas en RESGUARDA:'];
        foreach ($zones as $zone) {
            $lines[] = '- '.$this->describeZone($zone);
        }

        return $lines;
    }

    private function describeZone(SafeZone $zone): string
    {
        return sprintf(
            '%s (%s, distrito %s); estado: %s; ocupación %d de %d; %s',
            $zone->name,
            $zone->address,
            $zone->district->name,
            $zone->status_meta['label'],
            $zone->current_occupancy,
            $zone->capacity,
            $zone->is_verified ? 'verificada por RESGUARDA' : 'NO verificada',
        );
    }

    /**
     * @return list<string>
     */
    private function alertLines(?District $district): array
    {
        $alerts = Alert::query()
            ->with('district:id,name')
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->when($district, fn ($query) => $query->where(fn ($query) => $query->where('district_id', $district->id)->orWhereNull('district_id')))
            ->latest()
            ->limit(10)
            ->get();

        if ($alerts->isEmpty()) {
            return [$district !== null
                ? 'No hay alertas activas registradas en RESGUARDA para '.$district->name.'.'
                : 'No hay alertas activas registradas en RESGUARDA.'];
        }

        $lines = ['Alertas activas registradas en RESGUARDA:'];
        foreach ($alerts as $alert) {
            $lines[] = sprintf(
                '- %s (%s, severidad %s, distrito %s): %s',
                $alert->title,
                $alert->type,
                self::SEVERITY_LABELS[$alert->severity],
                $alert->district?->name ?? 'toda la provincia',
                mb_substr((string) $alert->message, 0, 240),
            );
        }

        return $lines;
    }
}
