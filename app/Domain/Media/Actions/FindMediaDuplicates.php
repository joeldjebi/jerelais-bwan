<?php

namespace App\Domain\Media\Actions;

use App\Domain\Access\Scopes\CommuneScope;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Models\Media;
use Illuminate\Support\Facades\DB;

/**
 * Détection de doublons (COO-13) : un média identique (même SHA-256) ou très proche
 * (empreinte perceptuelle à moins de 8 bits) déjà envoyé, par le même Relais ou un autre, toutes communes confondues.
 * Pour un relais hors du périmètre de l'utilisateur, l'écran n'affiche que la commune, le statut et la date.
 */
class FindMediaDuplicates
{
    public const SIMILARITY_THRESHOLD = 8;

    /**
     * @return list<array{media: Media, other: Media, identical: bool, distance: int}>
     */
    public function handle(RelaisItem $relais): array
    {
        $matches = [];

        foreach ($relais->medias()->where('status', MediaStatus::Ready->value)->get() as $media) {
            $query = Media::query()
                ->with(['relaisItem' => fn ($relation) => $relation->withoutGlobalScope(CommuneScope::class)->with('commune')])
                ->where('relais_item_id', '!=', $relais->id)
                ->where('status', MediaStatus::Ready->value)
                ->where(function ($inner) use ($media): void {
                    if ($media->sha256 !== null) {
                        $inner->orWhere('sha256', $media->sha256);
                    }

                    if ($media->phash !== null) {
                        $inner->orWhereRaw("phash is not null and bit_count(('x' || phash)::bit(64) # ('x' || ?)::bit(64)) <= ?", [$media->phash, self::SIMILARITY_THRESHOLD]);
                    }
                });

            if ($media->sha256 === null && $media->phash === null) {
                continue;
            }

            foreach ($query->limit(10)->get() as $other) {
                $identical = $media->sha256 !== null && $other->sha256 === $media->sha256;
                $distance = $identical || $media->phash === null || $other->phash === null ? 0 : $this->distance($media->phash, $other->phash);

                $matches[] = ['media' => $media, 'other' => $other, 'identical' => $identical, 'distance' => $distance];
            }
        }

        return $matches;
    }

    private function distance(string $a, string $b): int
    {
        $row = DB::selectOne("select bit_count(('x' || ?)::bit(64) # ('x' || ?)::bit(64)) as distance", [$a, $b]);

        return (int) ($row->distance ?? 64);
    }
}
