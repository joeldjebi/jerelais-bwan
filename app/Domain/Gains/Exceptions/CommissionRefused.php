<?php

namespace App\Domain\Gains\Exceptions;

use RuntimeException;

/**
 * Paiement ou annulation impossible (déjà payée, identité non vérifiée…). Message prêt à afficher.
 */
class CommissionRefused extends RuntimeException {}
