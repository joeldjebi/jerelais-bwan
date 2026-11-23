<?php

namespace App\Domain\Gains\Exceptions;

use RuntimeException;

/**
 * Le prestataire de paiement n'a pas pu envoyer un retrait.
 */
class PayoutFailed extends RuntimeException {}
