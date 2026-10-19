<?php

namespace App\Domain\Media\Antivirus;

use App\Domain\Media\Contracts\VirusScanner;
use App\Domain\Media\Exceptions\VirusScanUnavailable;

/**
 * Client clamd (protocole INSTREAM sur TCP) : le fichier est envoyé par blocs, clamd répond OK ou FOUND.
 */
class ClamAvScanner implements VirusScanner
{
    private const CHUNK_BYTES = 1_048_576;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly int $timeoutSeconds = 30,
    ) {}

    public function scan(string $path): ?string
    {
        $file = @fopen($path, 'rb');

        if ($file === false) {
            throw VirusScanUnavailable::because('fichier illisible');
        }

        $socket = @stream_socket_client("tcp://{$this->host}:{$this->port}", $errorCode, $errorMessage, $this->timeoutSeconds);

        if ($socket === false) {
            fclose($file);

            throw VirusScanUnavailable::because("clamd injoignable ({$errorMessage})");
        }

        try {
            stream_set_timeout($socket, $this->timeoutSeconds);
            fwrite($socket, "zINSTREAM\0");

            while (! feof($file)) {
                $chunk = fread($file, self::CHUNK_BYTES);

                if ($chunk === false || $chunk === '') {
                    break;
                }

                fwrite($socket, pack('N', strlen($chunk)).$chunk);
            }

            fwrite($socket, pack('N', 0));
            $response = trim((string) stream_get_contents($socket), "\0\n ");
        } finally {
            fclose($file);
            fclose($socket);
        }

        if (str_ends_with($response, 'OK')) {
            return null;
        }

        if (preg_match('/^stream: (.+) FOUND$/', $response, $matches) === 1) {
            return $matches[1];
        }

        throw VirusScanUnavailable::because($response === '' ? 'réponse vide' : $response);
    }
}
