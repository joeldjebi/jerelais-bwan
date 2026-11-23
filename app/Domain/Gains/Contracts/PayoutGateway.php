<?php

namespace App\Domain\Gains\Contracts;

use App\Domain\Gains\Exceptions\PayoutFailed;
use App\Domain\Gains\Models\Payout;

/**
 * Passerelle qui envoie un retrait sur le compte Mobile Money du Relais (MOB-26).
 */
interface PayoutGateway
{
    /**
     * Nom enregistré sur le retrait (« simulation », « manual », nom du prestataire).
     */
    public function name(): string;

    /**
     * Envoie la somme. Renvoie la référence de la transaction, ou null quand le retrait doit être payé à la main
     * depuis la console.
     *
     * @throws PayoutFailed envoi refusé par le prestataire (le retrait reste à traiter depuis la console)
     */
    public function send(Payout $payout): ?string;
}
