<?php

namespace App\Services;

/**
 * Safe template renderer for letter templates.
 * Uses a whitelist approach - only known placeholders are replaced.
 */
class LetterTemplateRenderer
{
    /**
     * Allowed placeholders with their descriptions.
     */
    public const ALLOWED_PLACEHOLDERS = [
        'unit_name' => 'Nama unit pengirim',
        'unit_code' => 'Kode unit pengirim',
        'today' => 'Tanggal hari ini (format: d F Y)',
        'today_short' => 'Tanggal hari ini (format: d/m/Y)',
        'month_year' => 'Bulan dan tahun (format: F Y)',
        'year' => 'Tahun',
        'letter_number' => 'Nomor surat (jika sudah ada)',
        'recipient_name' => 'Nama penerima (jika tipe anggota)',
        'recipient_unit' => 'Nama unit penerima (jika tipe unit)',
        'creator_name' => 'Nama pembuat surat',
    ];

    /**
     * Render a template string with given context.
     * Unknown placeholders are replaced with empty string.
     *
     * @param string $template The template string with {{placeholder}} syntax
     * @param array $context Key-value pairs for replacement
     * @return string Rendered content
     */
    public function render(string $template, array $context = []): string
    {
        // Only allow whitelisted placeholders
        $allowedKeys = array_keys(self::ALLOWED_PLACEHOLDERS);

        // Filter context to only allowed keys
        $safeContext = array_intersect_key($context, array_flip($allowedKeys));

        // Replace known placeholders
        $result = preg_replace_callback(
            '/\{\{(\w+)\}\}/',
            function ($matches) use ($safeContext) {
                $key = $matches[1];
                // Only replace if in whitelist, otherwise empty
                if (array_key_exists($key, $safeContext)) {
                    return $safeContext[$key] ?? '';
                }
                // Unknown placeholder = empty (safe behavior)
                return '';
            },
            $template
        );

        return $result;
    }

    /**
     * Build context from available data.
     * Only includes safe, non-sensitive information.
     */
    public function buildContext(array $data): array
    {
        $context = [];

        // Date placeholders
        $context['today'] = now()->translatedFormat('d F Y');
        $context['today_short'] = now()->format('d/m/Y');
        $context['month_year'] = now()->translatedFormat('F Y');
        $context['year'] = now()->format('Y');

        // Unit info (from fromUnit)
        if (isset($data['from_unit'])) {
            $context['unit_name'] = $data['from_unit']['name'] ?? '';
            $context['unit_code'] = $data['from_unit']['code'] ?? '';
        }

        // Letter number (if available)
        if (isset($data['letter_number'])) {
            $context['letter_number'] = $data['letter_number'];
        } else {
            $context['letter_number'] = '[Nomor akan diisi]';
        }

        // Recipient info
        if (isset($data['to_member'])) {
            $context['recipient_name'] = $data['to_member']['full_name'] ?? '';
        }
        if (isset($data['to_unit'])) {
            $context['recipient_unit'] = $data['to_unit']['name'] ?? '';
        }

        // Creator info
        if (isset($data['creator'])) {
            $context['creator_name'] = $data['creator']['name'] ?? '';
        }

        return $context;
    }

    /**
     * Get list of available placeholders for UI display.
     */
    public function getAvailablePlaceholders(): array
    {
        return self::ALLOWED_PLACEHOLDERS;
    }

    /**
     * Check if a template is safe (only uses whitelisted placeholders).
     */
    public function validateTemplate(string $template): array
    {
        $errors = [];
        $allowedKeys = array_keys(self::ALLOWED_PLACEHOLDERS);

        preg_match_all('/\{\{(\w+)\}\}/', $template, $matches);

        foreach ($matches[1] as $placeholder) {
            if (!in_array($placeholder, $allowedKeys, true)) {
                $errors[] = "Placeholder tidak dikenal: {{$placeholder}}";
            }
        }

        return $errors;
    }
}
