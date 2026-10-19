<?php

namespace App\Livewire\Console\Settings;

use App\Domain\Access\Enums\Permission;
use App\Domain\Settings\Settings;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Paramétrage de la plateforme (ADM-23). Chaque modification est auditée.
 */
#[Title('Paramètres')]
class Index extends Component
{
    /**
     * Valeurs du formulaire, indexées par clé de paramètre avec « __ » à la place des points
     * (les points ne sont pas utilisables dans les noms de propriétés Livewire).
     *
     * @var array<string, int|bool|string|null>
     */
    public array $values = [];

    public function mount(Settings $settings): void
    {
        $this->authorizeAccess();

        foreach ($settings->all() as $key => $value) {
            $this->values[self::field($key)] = is_int($value) || is_bool($value) || $value === null ? $value : (string) $value;
        }
    }

    public function save(string $group, Settings $settings): void
    {
        $this->authorizeAccess();

        $keys = array_values(array_filter(array_keys($settings->all()), fn (string $key): bool => str_starts_with($key, $group.'.')));
        $rules = [];

        foreach ($keys as $key) {
            $default = config('jerelais.settings')[$key];
            $rules['values.'.self::field($key)] = match (true) {
                is_bool($default) => ['boolean'],
                $default === null => ['nullable', 'integer', 'min:1', 'max:36500'],
                default => ['required', 'integer', 'min:1', 'max:100000'],
            };
        }

        $this->validate($rules, attributes: collect($keys)->mapWithKeys(fn (string $key) => ['values.'.self::field($key) => mb_strtolower(__('settings.labels.'.$key))])->all());

        /** @var User $actor */
        $actor = Auth::user();
        $changed = 0;

        foreach ($keys as $key) {
            $raw = $this->values[self::field($key)] ?? null;
            $default = config('jerelais.settings')[$key];
            $value = match (true) {
                is_bool($default) => (bool) $raw,
                $raw === null || $raw === '' => null,
                default => (int) $raw,
            };

            if ($settings->get($key) !== $value) {
                $settings->set($key, $value, $actor);
                $changed++;
            }
        }

        $this->dispatch('toast', message: $changed > 0
            ? trans_choice(':count paramètre enregistré.|:count paramètres enregistrés.', $changed)
            : __('Aucune modification à enregistrer.'));
    }

    /**
     * @return array<string, list<string>>
     */
    public function groups(): array
    {
        /** @var array<string, mixed> $settings */
        $settings = config('jerelais.settings', []);
        $groups = [];

        foreach (array_keys($settings) as $key) {
            $groups[strstr($key, '.', true) ?: $key][] = $key;
        }

        return $groups;
    }

    public static function field(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    public function render(): View
    {
        return view('livewire.console.settings.index');
    }

    private function authorizeAccess(): void
    {
        abort_unless(Auth::user()?->can(Permission::SettingsManage->value) === true, 403);
    }
}
