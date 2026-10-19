<?php

namespace App\Domain\Contribution\States;

use App\Domain\Contribution\Models\RelaisItem;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * Cycle de vie d'un relais (§8.1, schéma 5) : 10 statuts et les seules transitions permises.
 *
 * @extends State<RelaisItem>
 */
abstract class RelaisState extends State
{
    public function label(): string
    {
        return __('relais_items.states.'.static::getMorphClass());
    }

    /**
     * Statut affiché au Relais dans « Mes relais » (MOB-20) : l'examen interne et l'arbitrage restent internes.
     */
    public function publicName(): string
    {
        return match (static::class) {
            InReview::class, Sensitive::class => Received::$name,
            default => static::getMorphClass(),
        };
    }

    /**
     * Le relais attend une décision du coordonnateur.
     */
    public function isAwaitingValidation(): bool
    {
        return $this instanceof Received || $this instanceof InReview;
    }

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Uploading::class)
            ->allowTransition(Uploading::class, Received::class)
            ->allowTransition(Received::class, InReview::class)
            ->allowTransition(InReview::class, Received::class)
            ->allowTransition(InReview::class, Validated::class)
            ->allowTransition(InReview::class, Rejected::class)
            ->allowTransition(InReview::class, ComplementRequested::class)
            ->allowTransition(InReview::class, Sensitive::class)
            ->allowTransition(ComplementRequested::class, Received::class)
            ->allowTransition(Sensitive::class, Validated::class)
            ->allowTransition(Sensitive::class, Rejected::class)
            // Arbitrage de la rédaction (§8.2) : elle peut revenir sur une décision, la modification est tracée.
            ->allowTransition(Validated::class, Rejected::class)
            ->allowTransition(Rejected::class, Validated::class)
            ->allowTransition(Validated::class, Selected::class)
            ->allowTransition(Selected::class, Validated::class)
            ->allowTransition(Selected::class, Broadcast::class)
            ->allowTransition([Validated::class, Rejected::class, Broadcast::class], Archived::class);
    }
}
