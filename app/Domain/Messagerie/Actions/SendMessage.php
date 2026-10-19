<?php

namespace App\Domain\Messagerie\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\Contracts\SmsGateway;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Messagerie\Models\Conversation;
use App\Domain\Messagerie\Models\Message;
use App\Domain\Relais\Enums\RelaisStatus;
use App\Domain\Relais\Models\RelaisProfile;
use App\Domain\Territoire\Models\Commune;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Messagerie Relais ↔ équipe de la commune (MOB-22, COO-20) : conversation générale ou rattachée à un relais,
 * message groupé à tous les Relais d'une commune. Le Relais reçoit au plus un SMS de rappel par heure et par conversation.
 */
class SendMessage
{
    public const SMS_COOLDOWN_MINUTES = 60;

    public function __construct(
        private readonly SmsGateway $sms,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Conversation générale du Relais, ou celle d'un de ses relais.
     */
    public function conversationFor(RelaisProfile $profile, ?RelaisItem $relais = null): Conversation
    {
        if ($relais !== null && $relais->user_id !== $profile->user_id) {
            throw new InvalidArgumentException(__('conversations.errors.not_owner'));
        }

        return Conversation::withoutCommuneRestriction()->firstOrCreate(
            ['relais_profile_id' => $profile->id, 'relais_item_id' => $relais?->id],
            ['commune_id' => $relais->commune_id ?? $profile->commune_id],
        );
    }

    public function send(Conversation $conversation, User $sender, string $body, ?string $broadcastUuid = null): Message
    {
        $body = trim($body);

        if ($body === '') {
            throw new InvalidArgumentException(__('conversations.errors.empty'));
        }

        $fromStaff = $sender->id !== $conversation->relaisProfile->user_id;

        $message = DB::transaction(function () use ($conversation, $sender, $body, $fromStaff, $broadcastUuid): Message {
            $message = $conversation->messages()->create([
                'sender_id' => $sender->id,
                'from_staff' => $fromStaff,
                'body' => Str::limit($body, 2000, ''),
                'broadcast_uuid' => $broadcastUuid,
                'created_at' => now(),
            ]);

            $conversation->forceFill([
                'last_message_at' => $message->created_at,
                $fromStaff ? 'staff_read_at' : 'relais_read_at' => $message->created_at,
            ])->save();

            return $message;
        });

        if ($fromStaff) {
            $this->remindRelais($conversation);
        }

        return $message;
    }

    /**
     * Message groupé à tous les Relais actifs d'une commune (COO-20).
     */
    public function broadcast(Commune $commune, User $sender, string $body): int
    {
        $uuid = (string) Str::uuid7();
        $count = 0;

        RelaisProfile::withoutCommuneRestriction()
            ->where('commune_id', $commune->id)
            ->where('status', RelaisStatus::Active->value)
            ->with('user')
            ->each(function (RelaisProfile $profile) use ($sender, $body, $uuid, &$count): void {
                $this->send($this->conversationFor($profile), $sender, $body, $uuid);
                $count++;
            });

        $this->audit->record('message.broadcast', $commune, ['recipients' => $count], actor: $sender, communeId: $commune->id);

        return $count;
    }

    public function markRead(Conversation $conversation, User $reader): void
    {
        $field = $reader->id === $conversation->relaisProfile->user_id ? 'relais_read_at' : 'staff_read_at';
        $conversation->forceFill([$field => now()])->save();
    }

    private function remindRelais(Conversation $conversation): void
    {
        if ($conversation->relais_notified_at !== null && $conversation->relais_notified_at->gt(now()->subMinutes(self::SMS_COOLDOWN_MINUTES))) {
            return;
        }

        $phone = $conversation->relaisProfile->user->phoneNumber();

        if ($phone !== null) {
            $this->sms->send($phone, __('conversations.sms'));
            $conversation->forceFill(['relais_notified_at' => now()])->save();
        }
    }
}
