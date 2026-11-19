<?php

namespace App\Http\Resources\V1;

use App\Domain\Notification\Models\AnnouncementRecipient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Annonce reçue par le Relais (boîte de réception de l'application, MOB-21).
 *
 * @mixin AnnouncementRecipient
 */
class AnnouncementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $announcement = $this->announcement;

        return [
            'uuid' => $announcement->uuid,
            'title' => $announcement->title,
            'body' => $announcement->body,
            'screen' => $announcement->screen->value,
            'sent_at' => ($announcement->sent_at ?? $this->created_at)?->toIso8601ZuluString(),
            'read_at' => $this->read_at?->toIso8601ZuluString(),
            'is_read' => $this->read_at !== null,
        ];
    }
}
