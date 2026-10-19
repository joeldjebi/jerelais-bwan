<?php

namespace App\Domain\Audit\Support;

use App\Domain\Audit\Models\AuditLog;

/**
 * Traduit les entrées techniques du journal en phrases lisibles pour la console.
 */
class AuditPresenter
{
    /** @var array<string, array{0: string, 1: string, 2: string}> libellé, icône, ton */
    private const ACTIONS = [
        'auth.login' => ['Connexion', 'log-out', 'neutral'],
        'auth.login.failed' => ['Échec de connexion', 'x', 'danger'],
        'auth.login.locked_out' => ['Connexion bloquée (trop de tentatives)', 'x', 'danger'],
        'auth.logout' => ['Déconnexion', 'log-out', 'neutral'],
        'auth.otp.requested' => ['Code SMS demandé', 'bell', 'neutral'],
        'auth.otp.failed' => ['Code SMS erroné', 'x', 'warning'],
        'auth.otp.locked' => ['Code SMS bloqué après 5 essais', 'x', 'danger'],
        'auth.account.created' => ['Nouveau compte créé', 'users', 'success'],
        'auth.device.revoked' => ['Appareil déconnecté', 'x', 'warning'],
        'auth.password.changed' => ['Mot de passe modifié', 'shield', 'neutral'],
        'auth.2fa.enabled' => ['Double authentification activée', 'shield', 'success'],
        'auth.2fa.disabled' => ['Double authentification désactivée', 'shield', 'danger'],
        'auth.2fa.recovery_code_used' => ['Code de secours utilisé', 'shield', 'warning'],
        'users.admin_created' => ['Administrateur créé', 'users', 'success'],
        'users.created' => ['Membre de l’équipe ajouté', 'users', 'success'],
        'users.suspended' => ['Compte suspendu', 'x', 'danger'],
        'users.reactivated' => ['Compte réactivé', 'check', 'success'],
        'users.profile_updated' => ['Profil mis à jour', 'users', 'neutral'],
        'audit.verified' => ['Intégrité du journal vérifiée', 'shield', 'neutral'],
        'settings.updated' => ['Paramètre modifié', 'layers', 'accent'],
        'territoire.commune.opened' => ['Commune ouverte aux Relais', 'map-pin', 'success'],
        'territoire.commune.closed' => ['Commune fermée aux Relais', 'map-pin', 'warning'],
        'candidature.submitted' => ['Candidature déposée', 'inbox', 'accent'],
        'candidature.resubmitted' => ['Candidature complétée et redéposée', 'inbox', 'accent'],
        'candidature.review_started' => ['Examen d’une candidature commencé', 'inbox', 'neutral'],
        'candidature.accepted' => ['Candidature acceptée', 'check', 'success'],
        'candidature.rejected' => ['Candidature refusée', 'x', 'danger'],
        'candidature.on_hold' => ['Candidature mise en attente', 'clock', 'warning'],
        'candidature.complement_requested' => ['Complément demandé au candidat', 'inbox', 'warning'],
        'candidature.document.uploaded' => ['Pièce de candidature reçue', 'photo', 'neutral'],
        'candidature.document.deleted' => ['Pièce de candidature retirée', 'photo', 'neutral'],
        'candidature.document.infected' => ['Fichier infecté refusé', 'x', 'danger'],
        'formation.quiz.attempted' => ['Quiz de formation passé', 'book', 'neutral'],
        'media.received' => ['Média contrôlé et enregistré', 'photo', 'neutral'],
        'media.rejected' => ['Média refusé au contrôle', 'x', 'danger'],
        'relais.validated' => ['Relais validé', 'check', 'success'],
        'relais.rejected' => ['Relais rejeté', 'x', 'danger'],
        'relais.complement_requested' => ['Complément demandé au Relais', 'inbox', 'warning'],
        'relais.flagged_sensitive' => ['Relais transmis à la rédaction (sensible)', 'flag', 'warning'],
        'relais.escalated' => ['Relais en retard escaladé', 'clock', 'warning'],
        'relais.complement_received' => ['Complément envoyé par le Relais', 'inbox', 'accent'],
        'relais.arbitrated' => ['Arbitrage de la rédaction', 'flag', 'accent'],
        'relais.selected' => ['Relais sélectionné par la rédaction', 'check', 'success'],
        'relais.unselected' => ['Relais retiré de la sélection', 'x', 'neutral'],
        'relais.broadcast' => ['Relais diffusé', 'signal', 'success'],
        'relais.exported' => ['Médias d’origine téléchargés', 'layers', 'neutral'],
        'relais.export_derogation' => ['Export d’un relais non validé (dérogation)', 'shield', 'danger'],
        'editorial.collection_created' => ['Collection créée', 'layers', 'neutral'],
        'editorial.collection_added' => ['Relais ajouté à une collection', 'layers', 'neutral'],
        'editorial.collection_removed' => ['Relais retiré d’une collection', 'layers', 'neutral'],
        'appel.created' => ['Appel à relais créé', 'bell', 'accent'],
        'appel.approved' => ['Appel local approuvé', 'check', 'success'],
        'appel.closed' => ['Appel à relais clos', 'x', 'neutral'],
        'message.broadcast' => ['Message groupé aux Relais d’une commune', 'bell', 'neutral'],
        'relais.profile_suspended' => ['Relais suspendu', 'x', 'danger'],
        'relais.profile_struck_off' => ['Relais radié', 'x', 'danger'],
        'relais.profile_active' => ['Relais réintégré', 'check', 'success'],
        'relais.profile_transferred' => ['Relais transféré de commune', 'map-pin', 'accent'],
        'relais.showcased' => ['Relais publié sur la vitrine', 'sparkles', 'success'],
        'relais.showcase_withdrawn' => ['Relais retiré de la vitrine', 'x', 'neutral'],
        'reports.exported' => ['Rapport exporté', 'chart', 'neutral'],
        'relais.received' => ['Relais reçu', 'inbox', 'accent'],
        'relais.created' => ['Nouveau Relais', 'signal', 'success'],
        'legal.accepted' => ['Charte et conditions acceptées', 'flag', 'success'],
        'legal.published' => ['Nouvelle version d’un texte juridique', 'book', 'accent'],
        'candidature.document.viewed' => ['Pièce d’identité consultée', 'eye-off', 'warning'],
    ];

    public function label(AuditLog $entry): string
    {
        $label = __(self::ACTIONS[$entry->action][0] ?? $entry->action);
        $detail = $this->detail($entry);

        return $detail === null ? $label : "{$label} · {$detail}";
    }

    public function icon(AuditLog $entry): string
    {
        return self::ACTIONS[$entry->action][1] ?? 'shield';
    }

    public function tone(AuditLog $entry): string
    {
        return self::ACTIONS[$entry->action][2] ?? 'neutral';
    }

    /**
     * @return array<string, string>
     */
    public static function actionOptions(): array
    {
        return array_map(fn (array $definition): string => __($definition[0]), self::ACTIONS);
    }

    private function text(string $key): string
    {
        $text = __($key);

        return is_string($text) ? $text : $key;
    }

    private function settingLabel(string $key): string
    {
        $label = __('settings.labels.'.$key);

        return is_string($label) ? $label : $key;
    }

    private function detail(AuditLog $entry): ?string
    {
        $payload = $entry->payload ?? [];

        return match (true) {
            isset($payload['name']) && is_string($payload['name']) => $payload['name'],
            $entry->action === 'settings.updated' && isset($payload['key']) && is_string($payload['key']) => $this->settingLabel($payload['key']),
            isset($payload['method']) && is_string($payload['method']) => match ($payload['method']) {
                'otp' => $this->text('code SMS'),
                'password' => $this->text('mot de passe'),
                'web' => $this->text('console'),
                default => null,
            },
            default => null,
        };
    }
}
