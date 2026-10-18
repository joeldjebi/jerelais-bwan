@php
    use App\Domain\Access\Enums\Role;

    $roleName = $user->getRoleNames()->first();
    $roleLabel = $roleName ? Role::from($roleName)->label() : '';
    $isCoordination = $user->hasAnyRole([Role::Coordonnateur->value, Role::Superviseur->value]);
@endphp

<x-layouts.console :title="__('Accueil')" :section="$isCoordination ? 'coordinateur' : 'console'" :user-name="$user->name ?? $user->email" :user-role="$roleLabel">
    <div class="mx-auto max-w-5xl">
        <p class="eyebrow">{{ $isCoordination ? __('Back-office coordonnateur') : __('Console REFLET TV') }}</p>
        <h1 class="mt-2 text-3xl font-semibold sm:text-4xl">{{ __('Bonjour, :name.', ['name' => Str::before($user->name ?? '', ' ') ?: __('bienvenue')]) }}</h1>
        <p class="mt-2 text-mute">{{ __('Votre espace est prêt. Les modules de validation, de suivi et d’exploitation arrivent au fil des étapes du projet.') }}</p>

        <div class="mt-8 grid gap-4 sm:grid-cols-3">
            <div class="card p-5">
                <p class="text-[0.8125rem] font-medium text-mute">{{ __('Rôle') }}</p>
                <p class="mt-2 text-lg font-semibold">{{ $roleLabel }}</p>
            </div>
            <div class="card p-5">
                <p class="text-[0.8125rem] font-medium text-mute">{{ __('Périmètre') }}</p>
                <p class="mt-2 text-lg font-semibold">
                    {{ $user->communes->isNotEmpty() ? $user->communes->pluck('name')->join(', ') : __('National') }}
                </p>
            </div>
            <div class="card p-5">
                <p class="text-[0.8125rem] font-medium text-mute">{{ __('Sécurité') }}</p>
                <p class="mt-2 inline-flex items-center gap-2 text-lg font-semibold text-status-validated">
                    <x-ui.icon name="shield" class="size-5" />{{ __('Double authentification active') }}
                </p>
            </div>
        </div>
    </div>
</x-layouts.console>
