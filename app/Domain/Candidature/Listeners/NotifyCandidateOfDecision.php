<?php

namespace App\Domain\Candidature\Listeners;

use App\Domain\Auth\Contracts\SmsGateway;
use App\Domain\Candidature\Enums\CandidatureDecision;
use App\Domain\Candidature\Events\CandidatureDecided;

/**
 * Prévient le candidat par SMS. Le message ne contient aucune donnée sensible (ni motif, ni commentaire) :
 * le détail se consulte une fois connecté. La notification push s'ajoutera avec l'application.
 */
class NotifyCandidateOfDecision
{
    public function __construct(private readonly SmsGateway $sms) {}

    public function handle(CandidatureDecided $event): void
    {
        $phone = $event->candidature->user->phoneNumber();

        if ($phone === null || $event->decision === CandidatureDecision::Hold) {
            return;
        }

        $this->sms->send($phone, __("candidature.sms.{$event->decision->value}"));
    }
}
