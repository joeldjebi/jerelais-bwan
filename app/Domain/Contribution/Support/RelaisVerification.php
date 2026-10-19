<?php

namespace App\Domain\Contribution\Support;

use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Media\Models\Media;
use Carbon\CarbonImmutable;

/**
 * Éléments du panneau de vérification (COO-12) : carte, écarts de lieu et de date, origine des médias,
 * historique et fiabilité du Relais (indicateur interne, jamais public).
 */
class RelaisVerification
{
    public const MAP_ZOOM = 16;

    public const OLD_CONTENT_HOURS = 24;

    public const LOCATION_GAP_METERS = 1000;

    /**
     * Mosaïque de tuiles OpenStreetMap 3 × 3 centrée sur le point, et position du repère en pixels.
     *
     * @return array{tiles: list<array{url: string, left: int, top: int}>, marker_left: float, marker_top: float, osm_url: string}|null
     */
    public function map(RelaisItem $relais): ?array
    {
        if ($relais->location === null) {
            return null;
        }

        $lat = $relais->location->getLatitude();
        $lng = $relais->location->getLongitude();
        $n = 2 ** self::MAP_ZOOM;
        $x = ($lng + 180) / 360 * $n;
        $y = (1 - log(tan(deg2rad($lat)) + 1 / cos(deg2rad($lat))) / M_PI) / 2 * $n;
        $tileX = (int) floor($x);
        $tileY = (int) floor($y);
        $tiles = [];

        foreach ([-1, 0, 1] as $row) {
            foreach ([-1, 0, 1] as $column) {
                $server = ['a', 'b', 'c'][abs($tileX + $column + $tileY + $row) % 3];
                $tiles[] = [
                    'url' => "https://{$server}.tile.openstreetmap.org/".self::MAP_ZOOM.'/'.($tileX + $column).'/'.($tileY + $row).'.png',
                    'left' => ($column + 1) * 256,
                    'top' => ($row + 1) * 256,
                ];
            }
        }

        return [
            'tiles' => $tiles,
            'marker_left' => 256 + ($x - $tileX) * 256,
            'marker_top' => 256 + ($y - $tileY) * 256,
            'osm_url' => sprintf('https://www.openstreetmap.org/?mlat=%.6f&mlon=%.6f#map=17/%.6f/%.6f', $lat, $lng, $lat, $lng),
        ];
    }

    /**
     * Alertes de cohérence entre ce que dit le Relais, l'appareil et les fichiers.
     *
     * @return list<string>
     */
    public function warnings(RelaisItem $relais): array
    {
        $warnings = [];
        $received = $relais->received_at ?? CarbonImmutable::now();

        foreach ($relais->medias as $media) {
            if ($media->captured_at !== null && $media->captured_at->diffInHours($received, true) > self::OLD_CONTENT_HOURS) {
                $warnings[] = __('relais_items.warnings.old_content', ['date' => $media->captured_at->translatedFormat('j F Y à H:i')]);
            }

            $gap = $this->fileLocationGap($relais, $media);

            if ($gap !== null && $gap > self::LOCATION_GAP_METERS) {
                $warnings[] = __('relais_items.warnings.location_gap', ['distance' => number_format($gap / 1000, 1, ',', ' ')]);
            }
        }

        if ($relais->medias->contains(fn (Media $media): bool => $media->is_imported)) {
            $warnings[] = __('relais_items.warnings.imported');
        }

        if ($relais->routed_by === 'declared') {
            $warnings[] = __('relais_items.warnings.declared_commune');
        }

        if ($relais->location_accuracy_m !== null && $relais->location_accuracy_m > 200) {
            $warnings[] = __('relais_items.warnings.imprecise', ['meters' => $relais->location_accuracy_m]);
        }

        return array_values(array_unique($warnings));
    }

    /**
     * Distance (mètres) entre la position GPS envoyée et celle inscrite dans le fichier.
     */
    public function fileLocationGap(RelaisItem $relais, Media $media): ?float
    {
        $gps = $media->metadata['gps'] ?? null;

        if ($relais->location === null || ! is_array($gps) || ! isset($gps['lat'], $gps['lng'])) {
            return null;
        }

        return $this->haversine($relais->location->getLatitude(), $relais->location->getLongitude(), (float) $gps['lat'], (float) $gps['lng']);
    }

    /**
     * Historique du Relais : envois, validations, rejets et taux de fiabilité.
     *
     * @return array{sent: int, validated: int, rejected: int, reliability: int|null}
     */
    public function relaisStats(RelaisItem $relais): array
    {
        $states = RelaisItem::withoutCommuneRestriction()
            ->where('user_id', $relais->user_id)
            ->whereNotNull('submitted_at')
            ->pluck('state')
            ->map(fn (mixed $state): string => is_object($state) && method_exists($state, 'getValue') ? $state->getValue() : (string) $state);

        $validated = $states->filter(fn (string $state): bool => in_array($state, ['validated', 'selected', 'broadcast', 'archived'], true))->count();
        $rejected = $states->filter(fn (string $state): bool => $state === 'rejected')->count();

        return [
            'sent' => $states->count(),
            'validated' => $validated,
            'rejected' => $rejected,
            'reliability' => $validated + $rejected === 0 ? null : (int) round($validated / ($validated + $rejected) * 100),
        ];
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 6_371_000 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
