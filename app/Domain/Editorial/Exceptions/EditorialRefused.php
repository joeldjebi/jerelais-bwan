<?php

namespace App\Domain\Editorial\Exceptions;

use RuntimeException;

/**
 * Action éditoriale impossible dans l'état actuel du relais (ou motif manquant).
 */
class EditorialRefused extends RuntimeException {}
