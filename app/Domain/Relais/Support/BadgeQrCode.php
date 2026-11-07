<?php

namespace App\Domain\Relais\Support;

use App\Domain\Relais\Models\RelaisProfile;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\HtmlString;

/**
 * QR code du badge (MOB-07) : il encode l'adresse publique de vérification, rien d'autre.
 * Généré avec bacon/bacon-qr-code, déjà présent pour la double authentification (Fortify).
 */
class BadgeQrCode
{
    public static function svg(RelaisProfile $profile, int $size = 176): HtmlString
    {
        $svg = (new Writer(new ImageRenderer(
            new RendererStyle($size, 0, null, null, Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(29, 29, 31))),
            new SvgImageBackEnd,
        )))->writeString($profile->verificationUrl());

        return new HtmlString(trim(substr($svg, (int) strpos($svg, "\n") + 1)));
    }
}
