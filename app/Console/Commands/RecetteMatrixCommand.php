<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('recette:matrice {--check : Échoue si une exigence indispensable côté serveur n’a aucun test automatisé}')]
#[Description('Génère le cahier de recette (docs/recette/cahier-de-recette.md) : chaque exigence, son scénario manuel et les tests qui la citent')]
class RecetteMatrixCommand extends Command
{
    private const STATUSES = [
        'auto' => 'Automatisé + manuel',
        'manual' => 'Manuel',
        'mobile' => 'Équipe mobile',
        'out' => 'Hors lot',
    ];

    public function handle(): int
    {
        /** @var list<array{id: string, titre: string, priorite: string, perimetre: string, scenario: string, note: string}> $requirements */
        $requirements = json_decode(File::get(base_path('docs/recette/exigences.json')), true, flags: JSON_THROW_ON_ERROR);
        $tests = $this->testsByRequirement();
        $rows = [];
        $gaps = [];

        foreach ($requirements as $requirement) {
            $cited = $tests[$requirement['id']] ?? [];
            $status = match (true) {
                $requirement['perimetre'] === 'hors-lot' => 'out',
                $cited !== [] => 'auto',
                $requirement['perimetre'] === 'mobile' => 'mobile',
                default => 'manual',
            };

            if ($status === 'manual' && $requirement['priorite'] === 'Indispensable') {
                $gaps[] = $requirement['id'];
            }

            $rows[] = [...$requirement, 'status' => $status, 'tests' => $cited];
        }

        File::put(base_path('docs/recette/cahier-de-recette.md'), $this->render($rows));

        $counts = collect($rows)->countBy('status');
        $this->components->info('Cahier de recette généré : '.count($rows).' exigences, '.($counts['auto'] ?? 0).' couvertes par des tests, '.($counts['mobile'] ?? 0).' côté équipe mobile, '.($counts['out'] ?? 0).' hors lot.');

        if ($gaps !== []) {
            $this->components->warn('Indispensables sans test automatisé : '.implode(', ', $gaps));

            return $this->option('check') ? self::FAILURE : self::SUCCESS;
        }

        return self::SUCCESS;
    }

    /**
     * Repère les tests Pest qui citent une exigence entre crochets : it('… [MOB-01, ADM-25]').
     *
     * @return array<string, list<string>>
     */
    private function testsByRequirement(): array
    {
        $found = [];

        foreach (File::allFiles(base_path('tests')) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            preg_match_all("/\\bit\\(\\s*(['\"])(.+?)\\1\\s*,/u", $file->getContents(), $matches);

            foreach ($matches[2] as $description) {
                preg_match_all('/\b(?:MOB|WEB|COO|ADM)-\d{2}\b/', $description, $ids);

                foreach (array_unique($ids[0]) as $id) {
                    $found[$id][] = $file->getRelativePathname().' — '.stripslashes($description);
                }
            }
        }

        return $found;
    }

    /**
     * @param  list<array{id: string, titre: string, priorite: string, perimetre: string, scenario: string, note: string, status: string, tests: list<string>}>  $rows
     */
    private function render(array $rows): string
    {
        $counts = collect($rows)->countBy('status');
        $lines = [
            '# Cahier de recette — JE RELAIS',
            '',
            '> Généré par `php artisan recette:matrice` le '.now()->timezone('Africa/Abidjan')->format('d/m/Y à H:i').'. Ne pas modifier à la main : éditer `docs/recette/exigences.json` ou les tests, puis régénérer.',
            '',
            '**Critère de sortie de l’étape 6** : aucune anomalie bloquante ni majeure ouverte. Chaque exigence est recettée sur la préproduction par le scénario manuel ; les tests automatisés en garantissent la non-régression.',
            '',
            '| Statut | Exigences |',
            '|---|---|',
        ];

        foreach (self::STATUSES as $key => $label) {
            $lines[] = "| {$label} | ".($counts[$key] ?? 0).' |';
        }

        $lines = [...$lines, '', '## Matrice de traçabilité', '', '| Exigence | Priorité | Périmètre | Statut | Tests | Résultat |', '|---|---|---|---|---|---|'];

        foreach ($rows as $row) {
            $lines[] = "| [{$row['id']}](#".strtolower($row['id']).") | {$row['priorite']} | {$row['perimetre']} | ".self::STATUSES[$row['status']].' | '.count($row['tests']).' | ☐ |';
        }

        $lines = [...$lines, '', '## Scénarios par exigence', ''];

        foreach ($rows as $row) {
            $lines[] = "### {$row['id']}";
            $lines[] = '';
            $lines[] = "*{$row['priorite']} · {$row['perimetre']} · ".self::STATUSES[$row['status']].'*';
            $lines[] = '';
            $lines[] = '> '.$row['titre'];
            $lines[] = '';
            $lines[] = '**Scénario manuel** : '.$row['scenario'];

            if ($row['note'] !== '') {
                $lines[] = '';
                $lines[] = '**Note** : '.$row['note'];
            }

            if ($row['tests'] !== []) {
                $lines[] = '';
                $lines[] = '**Tests automatisés**';
                $lines[] = '';

                foreach ($row['tests'] as $test) {
                    $lines[] = "- `{$test}`";
                }
            }

            $lines = [...$lines, '', '**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______', ''];
        }

        return implode("\n", $lines);
    }
}
