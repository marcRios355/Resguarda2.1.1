<?php

namespace App\Services;

use App\Models\CitizenEvidence;
use App\Models\NeedHelpRequest;
use App\Models\Report;
use App\Models\SafeZone;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Datos en vivo del mapa: espacios abiertos reales de OpenStreetMap,
 * aglomeraciones estimadas con la actividad reciente de la plataforma y
 * mosaicos de tráfico en tiempo real del proveedor configurado.
 */
class LiveMapData
{
    /** Límites de la provincia de Trujillo: sur, oeste, norte, este. */
    public const BOUNDS = [-8.25, -79.16, -7.95, -78.75];

    /** Horas de actividad reciente que cuentan para estimar aglomeraciones. */
    public const RECENT_HOURS = 6;

    /** Radio en metros de calles pintadas alrededor de cada nivel de aglomeración. */
    private const STREET_RADIUS = ['baja' => 200, 'media' => 300, 'alta' => 400];

    /** Pausa tras un fallo de OpenStreetMap para no saturar el servicio. */
    private const STREETS_RETRY_KEY = 'live-map.streets.retry-after';

    private const OPEN_SPACES_KEY = 'live-map.open-spaces';

    private const OPEN_SPACES_BACKUP_KEY = 'live-map.open-spaces.backup';

    /**
     * Espacios abiertos (parques, plazas y puntos de reunión) registrados en
     * OpenStreetMap. Se guardan 24 horas porque la consulta tarda varios
     * segundos; si falla se devuelve la última copia válida.
     *
     * @return list<array{id: string, name: string, type: string, type_label: string, latitude: float, longitude: float}>
     */
    public function openSpaces(): array
    {
        $cached = Cache::get(self::OPEN_SPACES_KEY);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $spaces = $this->fetchOpenSpaces();
        } catch (Throwable $exception) {
            Log::warning('No se pudieron obtener los espacios abiertos de OpenStreetMap.', ['error' => $exception->getMessage()]);

            return Cache::get(self::OPEN_SPACES_BACKUP_KEY, []);
        }

        Cache::put(self::OPEN_SPACES_KEY, $spaces, now()->addDay());
        Cache::forever(self::OPEN_SPACES_BACKUP_KEY, $spaces);

        return $spaces;
    }

    /**
     * Zonas con concentración de personas estimada a partir de datos reales de
     * la plataforma: ocupación de las zonas seguras y reportes, evidencias y
     * pedidos de ayuda de las últimas horas, agrupados en celdas de ~300 m.
     *
     * @return list<array{latitude: float, longitude: float, level: string, level_label: string, weight: int, sources: list<string>}>
     */
    public function crowdHotspots(): array
    {
        $since = now()->subHours(self::RECENT_HOURS);
        $points = [];

        SafeZone::query()->get(['name', 'latitude', 'longitude', 'capacity', 'current_occupancy'])
            ->each(function (SafeZone $zone) use (&$points): void {
                $percentage = $zone->occupancy_percentage;

                if ($percentage >= 50) {
                    $points[] = [(float) $zone->latitude, (float) $zone->longitude, $percentage >= 90 ? 6 : ($percentage >= 70 ? 4 : 2), "Ocupación de {$zone->name}: {$percentage} %"];
                }
            });

        Report::query()
            ->where('created_at', '>=', $since)
            ->where('status', '!=', 'rejected')
            ->where('incident_type', '!=', 'zona_segura')
            ->get(['latitude', 'longitude', 'severity'])
            ->each(function (Report $report) use (&$points): void {
                $weight = ['baja' => 1, 'media' => 2, 'alta' => 3, 'critica' => 4][$report->severity] ?? 1;
                $points[] = [(float) $report->latitude, (float) $report->longitude, $weight, 'Reporte de vecinos'];
            });

        CitizenEvidence::query()
            ->where('created_at', '>=', $since)
            ->where('is_still_happening', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get(['latitude', 'longitude'])
            ->each(function (CitizenEvidence $evidence) use (&$points): void {
                $points[] = [(float) $evidence->latitude, (float) $evidence->longitude, 2, 'Evidencia ciudadana'];
            });

        NeedHelpRequest::query()
            ->where('created_at', '>=', $since)
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get(['latitude', 'longitude'])
            ->each(function (NeedHelpRequest $request) use (&$points): void {
                $points[] = [(float) $request->latitude, (float) $request->longitude, 3, 'Pedido de ayuda'];
            });

        $cells = [];

        foreach ($points as [$latitude, $longitude, $weight, $source]) {
            $key = round($latitude / 0.003).':'.round($longitude / 0.003);
            $cells[$key] ??= ['lat' => 0.0, 'lng' => 0.0, 'n' => 0, 'weight' => 0, 'sources' => []];
            $cells[$key]['lat'] += $latitude;
            $cells[$key]['lng'] += $longitude;
            $cells[$key]['n']++;
            $cells[$key]['weight'] += $weight;
            $cells[$key]['sources'][] = $source;
        }

        return array_values(array_map(function (array $cell): array {
            $level = $cell['weight'] >= 6 ? 'alta' : ($cell['weight'] >= 3 ? 'media' : 'baja');

            return [
                'latitude' => round($cell['lat'] / $cell['n'], 6),
                'longitude' => round($cell['lng'] / $cell['n'], 6),
                'level' => $level,
                'level_label' => ['alta' => 'Aglomeración alta', 'media' => 'Aglomeración media', 'baja' => 'Aglomeración baja'][$level],
                'weight' => $cell['weight'],
                'sources' => array_values(array_unique($cell['sources'])),
            ];
        }, $cells));
    }

    /**
     * Calles y pasajes reales (OpenStreetMap) que rodean cada aglomeración,
     * para pintarlos en el mapa con el color de su nivel. La geometría se
     * guarda 30 días por zona porque las calles casi no cambian; solo los
     * niveles de aglomeración se recalculan en cada consulta.
     *
     * @return list<array{latitude: float, longitude: float, level: string, level_label: string, sources: list<string>, streets: list<list<array{0: float, 1: float}>>|null}>
     */
    public function crowdStreets(): array
    {
        $hotspots = $this->crowdHotspots();
        $missing = [];

        foreach ($hotspots as $hotspot) {
            $key = $this->streetCellKey($hotspot['latitude'], $hotspot['longitude']);

            if (! Cache::has($key)) {
                $missing[$key] = $hotspot;
            }
        }

        // Se descarga una zona nueva por consulta para no exceder el tiempo de
        // respuesta; el mapa vuelve a pedir hasta completar todas las zonas.
        if ($missing !== [] && ! Cache::has(self::STREETS_RETRY_KEY)) {
            try {
                $this->fetchStreetsAround(array_slice($missing, 0, 1, true));
            } catch (Throwable $exception) {
                Cache::put(self::STREETS_RETRY_KEY, true, now()->addMinute());
                Log::warning('No se pudieron obtener las calles de OpenStreetMap.', ['error' => $exception->getMessage()]);
            }
        }

        return array_map(function (array $hotspot): array {
            $ways = Cache::get($this->streetCellKey($hotspot['latitude'], $hotspot['longitude']));
            $radius = self::STREET_RADIUS[$hotspot['level']];

            return [
                'latitude' => $hotspot['latitude'],
                'longitude' => $hotspot['longitude'],
                'level' => $hotspot['level'],
                'level_label' => $hotspot['level_label'],
                'sources' => $hotspot['sources'],
                'streets' => is_array($ways)
                    ? array_values(array_map(fn (array $way): array => $way['points'], array_filter($ways, fn (array $way): bool => $way['distance'] <= $radius)))
                    : null,
            ];
        }, $hotspots);
    }

    public function trafficEnabled(): bool
    {
        return filled(config('services.tomtom.key'));
    }

    /**
     * Mosaico PNG de tráfico en tiempo real (TomTom Traffic Flow): colorea las
     * calles según la velocidad actual frente a la velocidad libre. Se guarda
     * 90 segundos para no exceder la cuota gratuita del proveedor.
     */
    public function trafficTile(int $zoom, int $x, int $y): ?string
    {
        if (! $this->trafficEnabled()) {
            return null;
        }

        $key = "live-map.traffic.{$zoom}.{$x}.{$y}";
        $cached = Cache::get($key);

        if (is_string($cached)) {
            return base64_decode($cached, true) ?: null;
        }

        $style = config('services.tomtom.style', 'relative0');
        $response = $this->http(10)->get("https://api.tomtom.com/traffic/map/4/tile/flow/{$style}/{$zoom}/{$x}/{$y}.png", [
            'key' => config('services.tomtom.key'),
            'tileSize' => 256,
        ]);

        if (! $response->successful() || ! str_starts_with((string) $response->header('Content-Type'), 'image/')) {
            Log::warning('El proveedor de tráfico no devolvió un mosaico.', ['status' => $response->status()]);

            return null;
        }

        Cache::put($key, base64_encode($response->body()), now()->addSeconds(90));

        return $response->body();
    }

    /**
     * @return list<array{id: string, name: string, type: string, type_label: string, latitude: float, longitude: float}>
     */
    private function fetchOpenSpaces(): array
    {
        [$south, $west, $north, $east] = self::BOUNDS;
        $box = "({$south},{$west},{$north},{$east})";
        $query = "[out:json][timeout:60];(node[\"emergency\"=\"assembly_point\"]{$box};way[\"emergency\"=\"assembly_point\"]{$box};way[\"leisure\"=\"park\"][\"name\"]{$box};way[\"place\"=\"square\"]{$box};node[\"place\"=\"square\"]{$box};);out center 600;";

        $response = $this->http(70)->asForm()->post(config('services.overpass.url'), ['data' => $query])->throw();
        $labels = ['assembly_point' => 'Punto de reunión', 'park' => 'Parque', 'square' => 'Plaza'];
        $spaces = [];

        foreach ($response->json('elements', []) as $element) {
            $tags = $element['tags'] ?? [];
            $type = isset($tags['emergency']) ? 'assembly_point' : (isset($tags['leisure']) ? 'park' : 'square');
            $latitude = $element['lat'] ?? $element['center']['lat'] ?? null;
            $longitude = $element['lon'] ?? $element['center']['lon'] ?? null;

            if ($latitude === null || $longitude === null) {
                continue;
            }

            $spaces[] = [
                'id' => ($element['type'] ?? 'node').'/'.($element['id'] ?? count($spaces)),
                'name' => $tags['name'] ?? $labels[$type],
                'type' => $type,
                'type_label' => $labels[$type],
                'latitude' => (float) $latitude,
                'longitude' => (float) $longitude,
            ];
        }

        return $spaces;
    }

    /**
     * Descarga en una sola consulta las calles y pasajes que rodean las zonas
     * sin geometría guardada y asigna cada vía a las zonas cercanas.
     *
     * @param  array<string, array{latitude: float, longitude: float}>  $hotspots
     */
    private function fetchStreetsAround(array $hotspots): void
    {
        $radius = max(self::STREET_RADIUS);
        $types = 'trunk|primary|secondary|tertiary|unclassified|residential|living_street|pedestrian|footway|service|path|steps';
        $clauses = implode('', array_map(
            fn (array $h): string => "way(around:{$radius},{$h['latitude']},{$h['longitude']})[\"highway\"~\"^({$types})$\"];",
            $hotspots,
        ));

        $response = $this->http(100)->asForm()
            ->post(config('services.overpass.url'), ['data' => "[out:json][timeout:90];({$clauses});out geom;"])
            ->throw();

        $byCell = array_fill_keys(array_keys($hotspots), []);

        foreach ($response->json('elements', []) as $way) {
            $points = array_map(fn (array $p): array => [round($p['lat'], 5), round($p['lon'], 5)], $way['geometry'] ?? []);

            if (count($points) < 2) {
                continue;
            }

            foreach ($hotspots as $key => $hotspot) {
                $distance = min(array_map(fn (array $p): float => $this->metersBetween($hotspot['latitude'], $hotspot['longitude'], $p[0], $p[1]), $points));

                if ($distance <= $radius) {
                    $byCell[$key][] = ['distance' => (int) round($distance), 'points' => $points];
                }
            }
        }

        foreach ($byCell as $key => $ways) {
            Cache::put($key, $ways, now()->addDays(30));
        }
    }

    private function streetCellKey(float $latitude, float $longitude): string
    {
        return 'live-map.streets.'.round($latitude / 0.003).'.'.round($longitude / 0.003);
    }

    /** Distancia aproximada en metros (suficiente para radios de cientos de metros). */
    private function metersBetween(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $x = deg2rad($lng2 - $lng1) * cos(deg2rad(($lat1 + $lat2) / 2));
        $y = deg2rad($lat2 - $lat1);

        return sqrt($x * $x + $y * $y) * 6371000;
    }

    private function http(int $timeout): PendingRequest
    {
        $request = Http::timeout($timeout)->withUserAgent('RESGUARDA/1.0 (proyecto académico Tecsup Trujillo)');
        $bundle = config('services.gemini.ca_bundle');

        return filled($bundle) ? $request->withOptions(['verify' => $bundle]) : $request;
    }
}
