<?php

namespace App\Http\Resources\V1;

use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Support\SignedFileUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Relais vu par son auteur (MOB-20) : statut public, jamais la note interne ni le verrou.
 *
 * @mixin RelaisItem
 */
class RelaisItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $public = $this->state->publicName();

        return [
            'uuid' => $this->uuid,
            'client_uuid' => $this->client_uuid,
            'status' => $public,
            'status_label' => __("relais_items.states.{$public}"),
            'category' => $this->category === null ? null : ['uuid' => $this->category->uuid, 'name' => $this->category->name],
            'urgency' => $this->urgency->value,
            'appel' => $this->appel === null ? null : ['uuid' => $this->appel->uuid, 'title' => $this->appel->title],
            'title' => $this->title,
            'description' => $this->description,
            'location' => $this->location === null ? null : [
                'lat' => $this->location->getLatitude(),
                'lng' => $this->location->getLongitude(),
                'accuracy_m' => $this->location_accuracy_m,
            ],
            'commune' => $this->commune === null ? null : ['uuid' => $this->commune->uuid, 'name' => $this->commune->name],
            'declared_place' => $this->declared_place,
            'device_captured_at' => $this->device_captured_at?->toIso8601ZuluString(),
            'protections' => [
                'has_minors' => $this->has_minors,
                'people_consent' => $this->people_consent?->value,
                'credit_mode' => $this->credit_mode?->value,
                'credit_name' => $this->credit_name,
            ],
            'medias' => $this->whenLoaded('medias', fn () => $this->medias->map(fn (Media $media): array => $this->media($media))->values()),
            'rejection' => $public === 'rejected' ? ['reason' => $this->rejectionReason?->label, 'comment' => $this->decision_comment] : null,
            'complement_request' => $public === 'complement_requested' ? $this->decision_comment : null,
            'submitted_at' => $this->submitted_at?->toIso8601ZuluString(),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
        ];
    }

    /**
     * Fichier du relais, avec des liens signés (Wasabi) valables quelques minutes une fois le fichier contrôlé :
     * `url` (original) et `thumbnail_url` (vignette JPEG 640 px). À redemander après `urls_expire_at`.
     *
     * @return array<string, mixed>
     */
    private function media(Media $media): array
    {
        $minutes = (int) config('jerelais.media.view_url_minutes');
        $viewable = $media->status === MediaStatus::Ready && $media->purged_at === null;
        $thumbnail = $viewable && $media->relationLoaded('variants') ? $media->variants->firstWhere('kind', 'thumbnail') : null;

        return [
            'uuid' => $media->uuid,
            'client_uuid' => $media->client_uuid,
            'kind' => $media->kind->value,
            'status' => $media->status->value,
            'mime' => $media->mime ?? $media->declared_mime,
            'rejection' => $media->rejection === null ? null : __("media.rejections.{$media->rejection}"),
            'url' => $viewable ? SignedFileUrl::for($media->disk, $media->path, $minutes) : null,
            'thumbnail_url' => $thumbnail === null ? null : SignedFileUrl::for($thumbnail->disk, $thumbnail->path, $minutes),
            'urls_expire_at' => $viewable ? SignedFileUrl::expiresAt($minutes) : null,
        ];
    }
}
