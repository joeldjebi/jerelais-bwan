<?php

namespace App\Domain\Notification\Data;

/**
 * Contenu d'une notification push. Aucune donnée sensible : le détail se consulte dans l'application.
 *
 * `data` sert à l'application pour ouvrir le bon écran : `type` (ex. « relais.validated ») et l'uuid concerné.
 */
final readonly class PushMessage
{
    /** @var array<string, string> */
    public array $data;

    /**
     * @param  array<string, scalar|null>  $data
     */
    public function __construct(
        public string $title,
        public string $body,
        array $data = [],
    ) {
        $this->data = array_map(fn (mixed $value): string => (string) $value, array_filter($data, fn (mixed $value): bool => $value !== null));
    }
}
