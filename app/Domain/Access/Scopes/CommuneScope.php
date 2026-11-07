<?php

namespace App\Domain\Access\Scopes;

use App\Domain\Access\Support\CommuneAccess;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Limite automatiquement les requêtes aux communes du coordonnateur ou du superviseur connecté.
 * Première ligne de défense contre la fuite de données entre communes (Policies en complément).
 *
 * @template TModel of Model
 *
 * @implements Scope<TModel>
 */
class CommuneScope implements Scope
{
    /**
     * @param  Builder<covariant TModel>  $builder
     * @param  TModel  $model
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return;
        }

        $ids = app(CommuneAccess::class)->communeIdsFor($user);

        if ($ids !== null) {
            $builder->whereIn($model->qualifyColumn('commune_id'), $ids);
        }
    }
}
