<?php

namespace App\Console\Commands;

use App\Domain\Territoire\Actions\ImportCommunesFromGeoJson;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;
use JsonException;

#[Signature('territoire:import-communes
    {path : Chemin du fichier GeoJSON (FeatureCollection de polygones)}
    {--name-field=name : Propriété portant le nom de la commune}
    {--region-field= : Propriété portant le nom de la région}
    {--district-field= : Propriété portant le nom du district}
    {--code-field= : Propriété portant le code officiel}')]
#[Description('Importe ou met à jour les communes et leurs contours PostGIS depuis un GeoJSON')]
class ImportCommunesCommand extends Command
{
    public function handle(ImportCommunesFromGeoJson $import): int
    {
        $path = $this->argument('path');

        if (! is_string($path) || ! is_readable($path)) {
            $this->components->error('Fichier introuvable ou illisible : '.(is_string($path) ? $path : ''));

            return self::FAILURE;
        }

        try {
            $stats = $import->handle((string) file_get_contents($path), [
                'name' => $this->stringOption('name-field') ?? 'name',
                'region' => $this->stringOption('region-field'),
                'district' => $this->stringOption('district-field'),
                'code' => $this->stringOption('code-field'),
            ]);
        } catch (JsonException|InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Communes créées : {$stats['created']} · mises à jour : {$stats['updated']} · ignorées : {$stats['skipped']}");

        return self::SUCCESS;
    }

    private function stringOption(string $key): ?string
    {
        $value = $this->option($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
