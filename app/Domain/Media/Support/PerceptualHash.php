<?php

namespace App\Domain\Media\Support;

use GdImage;

/**
 * Empreinte perceptuelle « dHash » sur 64 bits (COO-13) : deux images très proches
 * (recompressées, redimensionnées) ont des empreintes à faible distance de Hamming.
 */
class PerceptualHash
{
    public static function fromImage(GdImage $image): string
    {
        $small = imagecreatetruecolor(9, 8);
        imagecopyresampled($small, $image, 0, 0, 0, 0, 9, 8, imagesx($image), imagesy($image));

        $bits = '';

        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                $bits .= self::luminance($small, $x, $y) > self::luminance($small, $x + 1, $y) ? '1' : '0';
            }
        }

        $hex = '';

        foreach (str_split($bits, 4) as $nibble) {
            $hex .= dechex((int) bindec($nibble));
        }

        return $hex;
    }

    /**
     * Nombre de bits différents entre deux empreintes (0 = identiques).
     */
    public static function distance(string $a, string $b): int
    {
        $distance = 0;

        foreach (str_split($a) as $index => $char) {
            $distance += substr_count(decbin(hexdec($char) ^ hexdec($b[$index] ?? '0')), '1');
        }

        return $distance;
    }

    private static function luminance(GdImage $image, int $x, int $y): float
    {
        $rgb = imagecolorat($image, $x, $y);

        return 0.299 * (($rgb >> 16) & 0xFF) + 0.587 * (($rgb >> 8) & 0xFF) + 0.114 * ($rgb & 0xFF);
    }
}
