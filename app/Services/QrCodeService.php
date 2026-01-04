<?php

namespace App\Services;

use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodeService
{
    /**
     * Generate QR image data using PNG (Imagick) when available, otherwise SVG.
     *
     * @return array{mime: string, data: string}|null
     */
    public function generate(string $text, int $size = 150, int $margin = 1): ?array
    {
        if (extension_loaded('imagick')) {
            try {
                $png = QrCode::format('png')
                    ->size($size)
                    ->margin($margin)
                    ->generate($text);
                return ['mime' => 'image/png', 'data' => $png];
            } catch (\Throwable $e) {
                // Fall through to SVG
            }
        }

        try {
            $svg = QrCode::format('svg')
                ->size($size)
                ->margin($margin)
                ->generate($text);
            return ['mime' => 'image/svg+xml', 'data' => $svg];
        } catch (\Throwable $e) {
            return null;
        }
    }
}
