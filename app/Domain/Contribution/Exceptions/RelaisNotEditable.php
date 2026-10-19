<?php

namespace App\Domain\Contribution\Exceptions;

use RuntimeException;

class RelaisNotEditable extends RuntimeException
{
    public static function make(): self
    {
        return new self(__('relais_items.errors.not_editable'));
    }
}
