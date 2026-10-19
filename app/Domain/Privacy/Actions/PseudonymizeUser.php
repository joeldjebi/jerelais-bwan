<?php

namespace App\Domain\Privacy\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Models\Device;
use App\Domain\Candidature\Models\Candidature;
use App\Domain\Contribution\Enums\CreditMode;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Messagerie\Models\Message;
use App\Domain\Relais\Enums\RelaisStatus;
use App\Domain\Relais\Models\RelaisProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Suppression d'un compte (§9.4) par pseudonymisation : l'identité, les pièces, le téléphone, les positions
 * et les messages disparaissent ; restent les traces exigées par la traçabilité (§9.5) et le journal d'audit,
 * désormais rattachés à un compte anonyme. Le numéro peut ensuite servir à un nouveau compte.
 */
class PseudonymizeUser
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(User $user, ?User $actor, string $reason): void
    {
        if ($user->status === UserStatus::Deleted) {
            return;
        }

        $files = [];

        DB::transaction(function () use ($user, $actor, $reason, &$files): void {
            $candidature = Candidature::withoutCommuneRestriction()->where('user_id', $user->id)->first();

            if ($candidature !== null) {
                foreach ($candidature->documents as $document) {
                    $files[] = [$document->disk, $document->path];
                    $document->delete();
                }

                $candidature->forceFill([
                    'last_name' => null, 'first_names' => null, 'birth_date' => null, 'gender' => null, 'email' => null,
                    'localite_id' => null, 'occupation' => null, 'languages' => null, 'interests' => null, 'motivation' => null,
                ])->save();
            }

            RelaisProfile::withoutCommuneRestriction()->where('user_id', $user->id)->get()->each(fn (RelaisProfile $profile) => $profile->forceFill([
                'display_name' => __('Relais supprimé'),
                'status' => RelaisStatus::StruckOff,
                'localite_id' => null,
                'badge_token' => Str::random(40),
                'suspended_at' => now(),
            ])->save());

            RelaisItem::withoutCommuneRestriction()->where('user_id', $user->id)->update([
                'location' => null,
                'location_accuracy_m' => null,
                'credit_mode' => CreditMode::Anonymous->value,
                'credit_name' => null,
                'device_model' => null,
            ]);

            Message::query()->where('sender_id', $user->id)->update(['body' => __('[message supprimé]')]);

            Device::query()->where('user_id', $user->id)->update(['fcm_token' => null, 'fcm_token_hash' => null, 'revoked_at' => now()]);
            $user->tokens()->delete();

            $user->forceFill([
                'name' => __('Compte supprimé'),
                'email' => null,
                'phone' => null,
                'phone_hash' => null,
                'password' => null,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'remember_token' => null,
                'status' => UserStatus::Deleted,
                'pseudonymized_at' => now(),
            ])->save();

            $this->audit->record('privacy.pseudonymized', $user, ['reason' => $reason], actor: $actor);
        });

        foreach ($files as [$disk, $path]) {
            Storage::disk($disk)->delete($path);
        }
    }
}
