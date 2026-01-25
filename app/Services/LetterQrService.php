<?php

namespace App\Services;

class LetterQrService
{
    protected QrCodeService $qrCodeService;

    public function __construct(QrCodeService $qrCodeService)
    {
        $this->qrCodeService = $qrCodeService;
    }

    /**
     * Generate QR code data (base64 and mime) for a given URL.
     *
     * @param string $url
     * @param int $size
     * @param int $margin
     * @return array|null Returns ['data' => string, 'mime' => string] or null on failure.
     */
    public function generate(string $url, int $size = 150, int $margin = 1): ?array
    {
        $qrData = $this->qrCodeService->generate($url, $size, $margin);

        if ($qrData) {
            return [
                'base64' => base64_encode($qrData['data']),
                'mime' => $qrData['mime'],
                'raw' => $qrData['data'], // Useful if raw bytes are needed
            ];
        }

        return null;
    }

    /**
     * Generate a fallback transparent image if QR generation fails.
     *
     * @param int $width
     * @param int $height
     * @param string $text
     * @return string Raw image data
     */
    public function generateFallbackImage(int $width = 150, int $height = 150, string $text = 'QR unavailable'): string
    {
        $img = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($img, 255, 255, 255);
        imagefill($img, 0, 0, $white);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagestring($img, 3, 10, 65, $text, $black);

        ob_start();
        imagepng($img);
        $data = ob_get_clean();
        imagedestroy($img);

        return $data;
    }
}
