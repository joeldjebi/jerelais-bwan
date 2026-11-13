<?php

namespace App\Domain\Identity\Support;

use App\Domain\Candidature\Models\Candidature;
use App\Domain\Identity\Enums\IdentityFileKind;
use App\Domain\Identity\Models\IdentityVerification;

/**
 * Éléments manquants avant l'envoi d'une vérification d'identité.
 */
class IdentityChecklist
{
    /**
     * @return array<string, string> élément manquant => message à afficher
     */
    public static function missing(IdentityVerification $verification): array
    {
        $missing = [];
        $identity = Candidature::withoutCommuneRestriction()->where('user_id', $verification->user_id)->first();

        foreach (['last_name', 'first_names', 'gender', 'birth_date'] as $field) {
            if (blank($identity?->getAttribute($field))) {
                $missing[$field] = __("identity.errors.{$field}");
            }
        }

        if ($verification->commune_id === null) {
            $missing['commune'] = __('identity.errors.commune');
        }

        if ($verification->document_type === null) {
            $missing['document_type'] = __('identity.errors.document_type');
        }

        $required = $verification->documentType?->requiredFiles() ?? [IdentityFileKind::Selfie];
        $present = $verification->files()->pluck('kind')->map(fn (mixed $kind): string => $kind instanceof IdentityFileKind ? $kind->value : (string) $kind)->all();

        foreach ($required as $kind) {
            if (! in_array($kind->value, $present, true)) {
                $missing['files.'.$kind->value] = __('identity.errors.file_missing', ['kind' => $kind->label()]);
            }
        }

        return $missing;
    }
}
