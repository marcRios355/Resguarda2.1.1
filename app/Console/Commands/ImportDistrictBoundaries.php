<?php

namespace App\Console\Commands;

use App\Models\District;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Carga límites distritales oficiales (por ejemplo, los publicados por INEI o
 * el portal de Datos Abiertos del Perú) en `districts.geojson_bounds`.
 */
#[Signature('districts:import-boundaries {file : Ruta a un GeoJSON FeatureCollection} {--property=NOMBDIST : Propiedad con el nombre del distrito}')]
#[Description('Importa los límites geográficos de los distritos desde un archivo GeoJSON')]
class ImportDistrictBoundaries extends Command
{
    public function handle(): int
    {
        $path = (string) $this->argument('file');

        if (! is_file($path)) {
            $this->error("No se encontró el archivo {$path}.");

            return self::FAILURE;
        }

        $geoJson = json_decode((string) file_get_contents($path), true);

        if (! is_array($geoJson) || ($geoJson['type'] ?? null) !== 'FeatureCollection' || ! is_array($geoJson['features'] ?? null)) {
            $this->error('El archivo debe ser un GeoJSON de tipo FeatureCollection.');

            return self::FAILURE;
        }

        $property = (string) $this->option('property');
        $districts = District::query()->get()->keyBy(fn (District $district): string => $this->normalize($district->name));
        $updated = 0;

        foreach ($geoJson['features'] as $feature) {
            $name = $feature['properties'][$property] ?? null;
            $geometry = $feature['geometry'] ?? null;

            if (! is_string($name) || ! is_array($geometry) || ! in_array($geometry['type'] ?? null, ['Polygon', 'MultiPolygon'], true)) {
                continue;
            }

            $district = $districts->get($this->normalize($name));

            if ($district === null) {
                continue;
            }

            $district->forceFill(['geojson_bounds' => $geometry])->save();
            $this->line("Límites actualizados: {$district->name}");
            $updated++;
        }

        $this->info("Distritos actualizados: {$updated} de {$districts->count()}.");

        return self::SUCCESS;
    }

    private function normalize(string $name): string
    {
        return Str::of($name)->ascii()->lower()->replaceMatches('/[^a-z]/', '')->toString();
    }
}
