<?php

namespace App\Console\Commands;

use App\Domain\Audit\AuditLogger;
use App\Domain\Exploitation\Support\PostgresTools;
use App\Domain\Media\Support\TemporaryPath;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

#[Signature('jerelais:backup')]
#[Description('Sauvegarde la base PostgreSQL (format custom, empreinte SHA-256) sur le disque de sauvegarde, puis applique la rétention')]
class BackupCommand extends Command
{
    public function handle(AuditLogger $audit): int
    {
        $disk = Storage::disk((string) config('jerelais.backup.disk'));
        $directory = (string) config('jerelais.backup.path');
        $name = 'jerelais-'.now()->format('Ymd-His').'.dump';
        $local = TemporaryPath::make('dump');

        try {
            PostgresTools::fromConfig()->dump($local);

            $size = (int) filesize($local);
            $checksum = (string) hash_file('sha256', $local);
            $stream = fopen($local, 'rb');

            if ($stream === false) {
                throw new RuntimeException("Impossible de relire {$local}.");
            }

            $disk->writeStream("{$directory}/{$name}", $stream);
            $disk->put("{$directory}/{$name}.sha256", "{$checksum}  {$name}\n");
        } catch (Throwable $exception) {
            report($exception);
            $this->components->error('Sauvegarde échouée : '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            if (isset($stream) && is_resource($stream)) {
                fclose($stream);
            }

            @unlink($local);
        }

        $removed = $this->applyRetention($directory);
        $audit->record('backup.created', payload: ['file' => $name, 'size' => $size, 'sha256' => $checksum, 'removed' => $removed]);

        $this->components->info("Sauvegarde {$name} (".number_format($size / 1048576, 1, ',', ' ').' Mo) ; '.$removed.' ancienne(s) supprimée(s).');

        return self::SUCCESS;
    }

    private function applyRetention(string $directory): int
    {
        $disk = Storage::disk((string) config('jerelais.backup.disk'));
        $limit = now()->subDays((int) config('jerelais.backup.keep_days'));
        $removed = 0;

        foreach ($disk->files($directory) as $file) {
            if (preg_match('/jerelais-(\d{8}-\d{6})\.dump$/', $file, $match) === 1 && Carbon::createFromFormat('Ymd-His', $match[1])?->lt($limit)) {
                $disk->delete([$file, $file.'.sha256']);
                $removed++;
            }
        }

        return $removed;
    }
}
