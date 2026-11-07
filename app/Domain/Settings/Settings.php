<?php

namespace App\Domain\Settings;

use App\Domain\Audit\AuditLogger;
use App\Domain\Settings\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Paramètres de la plateforme (ADM-23) : valeurs par défaut dans config/jerelais.php,
 * surcharges de l'administrateur en base, lecture mise en cache, chaque modification auditée.
 */
class Settings
{
    private const CACHE_KEY = 'jerelais.settings.overrides';

    public function __construct(private readonly AuditLogger $audit) {}

    public function get(string $key): mixed
    {
        $defaults = $this->defaults();

        if (! array_key_exists($key, $defaults)) {
            throw new InvalidArgumentException("Paramètre inconnu : {$key}");
        }

        $overrides = $this->overrides();

        return array_key_exists($key, $overrides) ? $overrides[$key] : $defaults[$key];
    }

    public function integer(string $key): int
    {
        return (int) $this->get($key);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return array_merge($this->defaults(), $this->overrides());
    }

    /**
     * Paramètres exposés à l'application mobile.
     *
     * @return array<string, mixed>
     */
    public function public(): array
    {
        /** @var list<string> $keys */
        $keys = config('jerelais.public_settings', []);

        return array_intersect_key($this->all(), array_flip($keys));
    }

    public function set(string $key, mixed $value, ?User $actor = null): void
    {
        $previous = $this->get($key);
        $this->assertSameType($key, $value);

        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => $actor?->id]);
        Cache::forget(self::CACHE_KEY);

        $this->audit->record('settings.updated', payload: ['key' => $key, 'old' => $previous, 'new' => $value], actor: $actor);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        /** @var array<string, mixed> $defaults */
        $defaults = config('jerelais.settings', []);

        return $defaults;
    }

    /**
     * @return array<string, mixed>
     */
    private function overrides(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn (): array => Setting::query()->pluck('value', 'key')->all());
    }

    private function assertSameType(string $key, mixed $value): void
    {
        $default = $this->defaults()[$key];

        $valid = match (true) {
            is_bool($default) => is_bool($value),
            is_int($default) => is_int($value) && $value >= 0,
            $default === null => $value === null || (is_int($value) && $value >= 0),
            default => is_string($value),
        };

        if (! $valid) {
            throw new InvalidArgumentException("Valeur invalide pour le paramètre {$key}.");
        }
    }
}
