<?php

namespace App\Domain\Auth\Exceptions;

/**
 * Plafond horaire de SMS de code atteint pour toute la plateforme (protection contre la fraude aux SMS).
 */
class SmsQuotaReached extends SmsNotSent
{
    public function userMessage(): string
    {
        return __('auth.sms_busy');
    }
}
