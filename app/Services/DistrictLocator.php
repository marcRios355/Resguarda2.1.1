<?php

namespace App\Services;

use App\Models\District;
use App\Models\SafeZone;
use Illuminate\Support\Collection;

/**
 * Resuelve en qué distrito de la provincia de Trujillo se encuentra un punto
 * y calcula distancias a los lugares registrados en RESGUARDA.
 *
 * Cuando un distrito tiene límites cargados en `geojson_bounds`, la ubicación
 * se verifica con un algoritmo de punto en polígono. Si ningún distrito tiene
 * límites que contengan el punto, se devuelve el distrito con el centroide más
 * cercano marcado como aproximado, para que el usuario lo confirme.
 */
class DistrictLocator
{
    /**
     * Distancia máxima, en kilómetros, desde el centroide más cercano para
     * considerar que un punto está dentro de la provincia.
     */
    public const MAX_PROVINCE_DISTANCE_KM = 25.0;

    /**
     * @return array{district: District|null, method: 'boundary'|'nearest_centroid'|'outside', approximate: bool, distance_km: float|null}
     */
    public function locate(float $latitude, float $longitude): array
    {
        $districts = District::query()->get();

        foreach ($districts as $district) {
            if ($this->boundsContain($district->geojson_bounds, $latitude, $longitude)) {
                return [
                    'district' => $district,
                    'method' => 'boundary',
                    'approximate' => false,
                    'distance_km' => round($this->distanceKm($latitude, $longitude, (float) $district->latitude, (float) $district->longitude), 2),
                ];
            }
        }

        $nearest = $districts
            ->map(fn (District $district): array => [
                'district' => $district,
                'distance' => $this->distanceKm($latitude, $longitude, (float) $district->latitude, (float) $district->longitude),
            ])
            ->sortBy('distance')
            ->first();

        if ($nearest === null || $nearest['distance'] > self::MAX_PROVINCE_DISTANCE_KM) {
            return [
                'district' => null,
                'method' => 'outside',
                'approximate' => true,
                'distance_km' => $nearest !== null ? round($nearest['distance'], 2) : null,
            ];
        }

        return [
            'district' => $nearest['district'],
            'method' => 'nearest_centroid',
            'approximate' => true,
            'distance_km' => round($nearest['distance'], 2),
        ];
    }

    /**
     * Zonas seguras ordenadas por cercanía al punto indicado.
     *
     * @return Collection<int, array{zone: SafeZone, distance_km: float}>
     */
    public function nearestSafeZones(float $latitude, float $longitude, int $limit = 5): Collection
    {
        return SafeZone::query()
            ->with('district:id,name')
            ->get()
            ->map(fn (SafeZone $zone): array => [
                'zone' => $zone,
                'distance_km' => round($this->distanceKm($latitude, $longitude, (float) $zone->latitude, (float) $zone->longitude), 2),
            ])
            ->sortBy('distance_km')
            ->take($limit)
            ->values();
    }

    /**
     * Distancia en línea recta (fórmula de Haversine) en kilómetros.
     */
    public function distanceKm(float $fromLatitude, float $fromLongitude, float $toLatitude, float $toLongitude): float
    {
        $earthRadiusKm = 6371.0;
        $latitudeDelta = deg2rad($toLatitude - $fromLatitude);
        $longitudeDelta = deg2rad($toLongitude - $fromLongitude);

        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($fromLatitude)) * cos(deg2rad($toLatitude)) * sin($longitudeDelta / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Acepta límites como lista de puntos `[{lat, lng}, ...]` (formato usado por
     * la aplicación) o como geometría GeoJSON `Polygon` / `MultiPolygon`.
     *
     * @param  array<mixed>|null  $bounds
     */
    public function boundsContain(?array $bounds, float $latitude, float $longitude): bool
    {
        foreach ($this->polygonsFrom($bounds) as $polygon) {
            if ($this->polygonContains($polygon, $latitude, $longitude)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<mixed>|null  $bounds
     * @return list<list<array{0: float, 1: float}>> Polígonos como listas de pares [lat, lng].
     */
    private function polygonsFrom(?array $bounds): array
    {
        if ($bounds === null || $bounds === []) {
            return [];
        }

        if (isset($bounds['type'], $bounds['geometry']) && $bounds['type'] === 'Feature') {
            $bounds = is_array($bounds['geometry']) ? $bounds['geometry'] : [];
        }

        $type = $bounds['type'] ?? null;

        if ($type === 'Polygon' && is_array($bounds['coordinates'] ?? null)) {
            $outerRing = $bounds['coordinates'][0] ?? [];

            return [$this->geoJsonRing(is_array($outerRing) ? $outerRing : [])];
        }

        if ($type === 'MultiPolygon' && is_array($bounds['coordinates'] ?? null)) {
            $polygons = [];
            foreach ($bounds['coordinates'] as $polygon) {
                $outerRing = is_array($polygon) ? ($polygon[0] ?? []) : [];
                $polygons[] = $this->geoJsonRing(is_array($outerRing) ? $outerRing : []);
            }

            return $polygons;
        }

        $points = [];
        foreach ($bounds as $point) {
            if (is_array($point) && is_numeric($point['lat'] ?? null) && is_numeric($point['lng'] ?? null)) {
                $points[] = [(float) $point['lat'], (float) $point['lng']];
            }
        }

        return count($points) >= 3 ? [$points] : [];
    }

    /**
     * GeoJSON guarda las coordenadas como [lng, lat].
     *
     * @param  array<mixed>  $ring
     * @return list<array{0: float, 1: float}>
     */
    private function geoJsonRing(array $ring): array
    {
        $points = [];
        foreach ($ring as $position) {
            if (is_array($position) && is_numeric($position[0] ?? null) && is_numeric($position[1] ?? null)) {
                $points[] = [(float) $position[1], (float) $position[0]];
            }
        }

        return $points;
    }

    /**
     * @param  list<array{0: float, 1: float}>  $polygon
     */
    private function polygonContains(array $polygon, float $latitude, float $longitude): bool
    {
        $inside = false;
        $count = count($polygon);

        if ($count < 3) {
            return false;
        }

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            [$latI, $lngI] = $polygon[$i];
            [$latJ, $lngJ] = $polygon[$j];

            $intersects = (($latI > $latitude) !== ($latJ > $latitude))
                && ($longitude < ($lngJ - $lngI) * ($latitude - $latI) / (($latJ - $latI) ?: PHP_FLOAT_EPSILON) + $lngI);

            if ($intersects) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }
}
