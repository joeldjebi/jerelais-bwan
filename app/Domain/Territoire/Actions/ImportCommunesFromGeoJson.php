<?php

namespace App\Domain\Territoire\Actions;

use App\Domain\Territoire\Models\Commune;
use App\Domain\Territoire\Models\Region;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Importe ou met à jour les communes et leurs contours depuis un GeoJSON (ADM-20).
 * Idempotent : une commune est retrouvée par son slug, son contour est remplacé.
 */
class ImportCommunesFromGeoJson
{
    /**
     * @param  array{name: string, region?: string|null, district?: string|null, code?: string|null}  $fields  noms des propriétés GeoJSON
     * @return array{created: int, updated: int, skipped: int}
     */
    public function handle(string $geoJson, array $fields): array
    {
        $data = json_decode($geoJson, true, flags: JSON_THROW_ON_ERROR);

        if (($data['type'] ?? null) !== 'FeatureCollection' || ! is_array($data['features'] ?? null)) {
            throw new InvalidArgumentException('Le fichier doit être une FeatureCollection GeoJSON.');
        }

        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        DB::transaction(function () use ($data, $fields, &$stats): void {
            foreach ($data['features'] as $feature) {
                $properties = $feature['properties'] ?? [];
                $name = trim((string) ($properties[$fields['name']] ?? ''));
                $geometryType = $feature['geometry']['type'] ?? null;

                if ($name === '' || ! in_array($geometryType, ['Polygon', 'MultiPolygon'], true)) {
                    $stats['skipped']++;

                    continue;
                }

                $region = $this->resolveRegion($properties, $fields);

                $commune = Commune::query()->firstOrNew(['slug' => Str::slug($name)]);
                $stats[$commune->exists ? 'updated' : 'created']++;

                $commune->fill([
                    'name' => $name,
                    'region_id' => $region !== null ? $region->id : $commune->region_id,
                    'code' => isset($fields['code']) ? ($properties[$fields['code']] ?? $commune->code) : $commune->code,
                ])->save();

                DB::update(
                    'update communes set geom = ST_Multi(ST_MakeValid(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326))) where id = ?',
                    [json_encode($feature['geometry'], JSON_THROW_ON_ERROR), $commune->id],
                );
            }
        });

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $properties
     * @param  array{name: string, region?: string|null, district?: string|null, code?: string|null}  $fields
     */
    private function resolveRegion(array $properties, array $fields): ?Region
    {
        $regionName = isset($fields['region']) ? trim((string) ($properties[$fields['region']] ?? '')) : '';

        if ($regionName === '') {
            return null;
        }

        return Region::query()->firstOrCreate(
            ['slug' => Str::slug($regionName)],
            [
                'name' => $regionName,
                'district' => isset($fields['district']) ? ($properties[$fields['district']] ?? null) : null,
            ],
        );
    }
}
