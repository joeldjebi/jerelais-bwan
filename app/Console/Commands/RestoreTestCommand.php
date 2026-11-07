<?php

namespace App\Console\Commands;

use App\Domain\Audit\AuditLogger;
use App\Domain\Audit\Support\AuditChainVerifier;
use App\Domain\Exploitation\Support\PostgresTools;
use App\Domain\Media\Support\TemporaryPath;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

#[Signature('jerelais:restore-test {file? : Nom de la sauvegarde (la plus récente par défaut)} {--keep : Conserver la base restaurée pour inspection}')]
#[Description('Restaure une sauvegarde dans une base jetable et la contrôle : empreinte, migrations, volumes, chaîne d’audit')]
class RestoreTestCommand extends Command
{
    private const DATABASE = 'jerelais_restore_test';

    /**
     * Tables dont le volume restauré est comparé à la base en service (au moment de la sauvegarde, il peut être inférieur).
     */
    private const TABLES = ['users', 'communes', 'candidatures', 'relais_profiles', 'relais_items', 'medias', 'messages', 'audit_logs'];

    public function handle(AuditLogger $audit, AuditChainVerifier $verifier): int
    {
        $disk = Storage::disk((string) config('jerelais.backup.disk'));
        $directory = (string) config('jerelais.backup.path');
        $argument = $this->argument('file');
        $name = is_string($argument) && $argument !== ''
            ? $argument
            : collect($disk->files($directory))->filter(fn (string $file) => str_ends_with($file, '.dump'))->sort()->last();

        if ($name === null) {
            $this->components->error('Aucune sauvegarde trouvée.');

            return self::FAILURE;
        }

        $name = basename($name);
        $local = TemporaryPath::make('dump');
        $tools = PostgresTools::fromConfig();
        $started = microtime(true);

        try {
            $source = $disk->readStream("{$directory}/{$name}");
            $target = fopen($local, 'wb');

            if ($source === null || $target === false) {
                throw new RuntimeException("Sauvegarde {$name} illisible.");
            }

            stream_copy_to_stream($source, $target);
            fclose($target);

            $expected = strtok((string) $disk->get("{$directory}/{$name}.sha256"), ' ');
            if ($expected !== hash_file('sha256', $local)) {
                throw new RuntimeException('Empreinte SHA-256 différente : sauvegarde corrompue.');
            }
            $this->components->task('Empreinte SHA-256 vérifiée');

            $tools->restore($local, self::DATABASE);
            $this->components->task('Restauration dans '.self::DATABASE);

            config(['database.connections.restore_check' => [...config('database.connections.pgsql'), 'database' => self::DATABASE]]);
            $restored = DB::connection('restore_check');

            $pending = DB::table('migrations')->count() - $restored->table('migrations')->count();
            $this->components->task('Migrations présentes ('.$restored->table('migrations')->count().')', fn () => $pending >= 0);

            $rows = [];
            foreach (self::TABLES as $table) {
                $rows[] = [$table, $restored->table($table)->count(), DB::table($table)->count()];
            }
            $this->table(['Table', 'Restaurée', 'En service'], $rows);

            $chain = $verifier->verify('restore_check');
            if ($chain['broken_at'] !== null) {
                throw new RuntimeException("Chaîne d’audit rompue à la ligne {$chain['broken_at']}.");
            }
            $this->components->task("Chaîne d’audit intègre ({$chain['checked']} lignes)");

            $postgis = (string) $restored->scalar('select postgis_version()');
            $this->components->task("PostGIS opérationnel ({$postgis})");
        } catch (Throwable $exception) {
            report($exception);
            $this->components->error('Test de restauration échoué : '.$exception->getMessage());
            $audit->record('backup.restore_failed', payload: ['file' => $name, 'error' => $exception->getMessage()]);

            return self::FAILURE;
        } finally {
            @unlink($local);
            DB::purge('restore_check');

            if (! $this->option('keep')) {
                rescue(fn () => $tools->drop(self::DATABASE), report: false);
            }
        }

        $seconds = (int) round(microtime(true) - $started);
        $audit->record('backup.restore_tested', payload: ['file' => $name, 'seconds' => $seconds, 'audit_rows' => $chain['checked']]);
        $this->components->info("Restauration de {$name} réussie en {$seconds} s.");

        return self::SUCCESS;
    }
}
