<?php

namespace App\Services;

use Dompdf\Dompdf;

class LetterPdfService
{
    /**
     * Generate PDF binary content for a given HTML string.
     *
     * @param string $html The full HTML content to render
     * @param string $paperSize 'A4' by default
     * @param string $orientation 'portrait' or 'landscape'
     * @return string Raw PDF binary data
     */
    public function generate(string $html, string $paperSize = 'A4', string $orientation = 'portrait'): string
    {
        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper($paperSize, $orientation);
        $dompdf->render();

        return $dompdf->output();
    }
}
