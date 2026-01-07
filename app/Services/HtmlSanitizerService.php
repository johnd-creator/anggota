<?php

namespace App\Services;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * HTML Sanitizer Service
 * 
 * Sanitizes HTML content with a strict whitelist approach to prevent XSS attacks.
 * Uses DOMDocument for parsing and filtering.
 */
class HtmlSanitizerService
{
    /**
     * Allowed HTML tags.
     */
    protected const ALLOWED_TAGS = [
        'p',
        'br',
        'strong',
        'b',
        'em',
        'i',
        'u',
        's',
        'ul',
        'ol',
        'li',
        'blockquote',
        'h2',
        'h3',
        'hr',
        'a',
        'span',
        // Table tags
        'table',
        'thead',
        'tbody',
        'tr',
        'th',
        'td',
        'colgroup',
        'col',
    ];

    /**
     * Allowed attributes per tag.
     */
    protected const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'target', 'rel'],
        'p' => ['style'],
        'h2' => ['style'],
        'h3' => ['style'],
        'span' => ['style'],
        // Table attributes
        'table' => ['class', 'style'],
        'th' => ['colspan', 'rowspan', 'style', 'data-colwidth'],
        'td' => ['colspan', 'rowspan', 'style', 'data-colwidth'],
        'col' => ['style', 'data-colwidth'],
        'colgroup' => [],
        'tr' => [],
        'thead' => [],
        'tbody' => [],
    ];

    /**
     * Dangerous tags to completely remove (including content).
     */
    protected const DANGEROUS_TAGS = [
        'script',
        'iframe',
        'object',
        'embed',
        'form',
        'input',
        'textarea',
        'select',
        'button',
        'link',
        'meta',
        'style',
        'base',
        'svg',
        'math',
        'applet',
        'frame',
        'frameset',
        'layer',
        'ilayer',
        'bgsound',
    ];

    /**
     * Allowed URL schemes for href.
     */
    protected const ALLOWED_SCHEMES = ['http', 'https', 'mailto'];

    /**
     * Allowed text-align values for style attribute.
     */
    protected const ALLOWED_TEXT_ALIGN = ['left', 'center', 'right', 'justify'];

    /**
     * Sanitize HTML content.
     *
     * @param string|null $html Raw HTML input
     * @return string Sanitized HTML
     */
    public function sanitize(?string $html): string
    {
        if (empty($html)) {
            return '';
        }

        // First, convert plain text to HTML if needed
        $html = $this->ensureHtml($html);

        // Create DOMDocument
        $dom = new DOMDocument('1.0', 'UTF-8');

        // Suppress errors from malformed HTML
        libxml_use_internal_errors(true);

        // Load HTML with proper encoding
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="sanitizer-wrapper">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();

        // Get the wrapper element
        $wrapper = $dom->getElementById('sanitizer-wrapper');
        if (!$wrapper) {
            return '';
        }

        // Process all nodes recursively
        $this->processNode($wrapper);

        // Extract inner HTML from wrapper
        $result = '';
        foreach ($wrapper->childNodes as $child) {
            $result .= $dom->saveHTML($child);
        }

        // Clean up artifacts
        $result = str_replace(['<?xml encoding="UTF-8">', '<?xml encoding="UTF-8" ?>'], '', $result);

        return trim($result);
    }

    /**
     * Process a DOM node and its children recursively.
     */
    protected function processNode(DOMNode $node): void
    {
        $nodesToRemove = [];

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                /** @var DOMElement $child */
                $tagName = strtolower($child->nodeName);

                // Remove dangerous tags completely
                if (in_array($tagName, self::DANGEROUS_TAGS, true)) {
                    $nodesToRemove[] = $child;
                    continue;
                }

                // If tag not in whitelist, replace with its content
                if (!in_array($tagName, self::ALLOWED_TAGS, true) && $tagName !== 'div') {
                    // Create document fragment with child's content
                    $fragment = $child->ownerDocument->createDocumentFragment();
                    while ($child->firstChild) {
                        $fragment->appendChild($child->firstChild);
                    }
                    $node->replaceChild($fragment, $child);
                    // Restart processing from parent
                    $this->processNode($node);
                    return;
                }

                // Sanitize attributes
                $this->sanitizeAttributes($child, $tagName);

                // Process children recursively
                $this->processNode($child);
            } elseif ($child->nodeType === XML_TEXT_NODE && $child instanceof \DOMText) {
                // Normalize whitespace to prevent large gaps/overflow in justified text
                $normalized = $this->normalizeWhitespace($child->nodeValue);
                if ($normalized !== $child->nodeValue) {
                    $child->nodeValue = $normalized;
                }
                continue;
            }
        }

        // Remove dangerous nodes
        foreach ($nodesToRemove as $nodeToRemove) {
            $node->removeChild($nodeToRemove);
        }
    }

    /**
     * Sanitize attributes of an element.
     */
    protected function sanitizeAttributes(DOMElement $element, string $tagName): void
    {
        $allowedAttrs = self::ALLOWED_ATTRIBUTES[$tagName] ?? [];
        $attributesToRemove = [];

        // Collect attributes to remove
        foreach ($element->attributes as $attr) {
            /** @var DOMAttr $attr */
            $attrName = strtolower($attr->name);

            // Always remove event handlers
            if (str_starts_with($attrName, 'on')) {
                $attributesToRemove[] = $attr->name;
                continue;
            }

            // Remove attributes not in whitelist
            if (!in_array($attrName, $allowedAttrs, true)) {
                $attributesToRemove[] = $attr->name;
                continue;
            }

            // Sanitize specific attributes
            if ($attrName === 'href') {
                $safeHref = $this->sanitizeHref($attr->value);
                if ($safeHref === null) {
                    $attributesToRemove[] = $attr->name;
                } else {
                    $element->setAttribute('href', $safeHref);
                }
            } elseif ($attrName === 'style') {
                $safeStyle = $this->sanitizeStyle($attr->value);
                if ($safeStyle === '') {
                    $attributesToRemove[] = $attr->name;
                } else {
                    $element->setAttribute('style', $safeStyle);
                }
            }
        }

        // Remove collected attributes
        foreach ($attributesToRemove as $attrName) {
            $element->removeAttribute($attrName);
        }

        // For anchor tags, ensure safe target and rel
        if ($tagName === 'a' && $element->hasAttribute('href')) {
            $element->setAttribute('target', '_blank');
            $element->setAttribute('rel', 'noopener noreferrer nofollow');
        }
    }

    /**
     * Sanitize href attribute value.
     * Only allows http, https, and mailto schemes.
     *
     * @return string|null Sanitized href or null if invalid
     */
    protected function sanitizeHref(string $href): ?string
    {
        $href = trim($href);

        // Reject empty
        if ($href === '') {
            return null;
        }

        // Reject javascript:, data:, vbscript:, etc.
        $lowerHref = strtolower($href);

        // Check for dangerous schemes
        if (preg_match('/^(javascript|data|vbscript|file):/i', $lowerHref)) {
            return null;
        }

        // Check for encoded javascript
        $decoded = html_entity_decode($href, ENT_QUOTES, 'UTF-8');
        if (preg_match('/^(javascript|data|vbscript|file):/i', strtolower($decoded))) {
            return null;
        }

        // Parse URL to validate scheme
        $parsed = parse_url($href);
        if (isset($parsed['scheme'])) {
            if (!in_array(strtolower($parsed['scheme']), self::ALLOWED_SCHEMES, true)) {
                return null;
            }
        }

        return $href;
    }

    /**
     * Sanitize style attribute.
     * Only allows text-align with specific values.
     */
    protected function sanitizeStyle(string $style): string
    {
        // Only allow text-align property
        if (preg_match('/text-align\s*:\s*(left|center|right|justify)/i', $style, $matches)) {
            return 'text-align: ' . strtolower($matches[1]);
        }

        return '';
    }

    /**
     * Normalize whitespace in text nodes.
     * - Converts non-breaking spaces to normal spaces
     * - Collapses repeated whitespace into single spaces
     */
    protected function normalizeWhitespace(string $text): string
    {
        if ($text === '') {
            return $text;
        }

        $text = str_replace("\xC2\xA0", ' ', $text);
        $text = preg_replace('/[ \t\r\n]+/u', ' ', $text) ?? $text;
        return $text;
    }

    /**
     * Ensure content is HTML. If plain text, convert to paragraphs.
     */
    protected function ensureHtml(string $content): string
    {
        // Check if content already has HTML structure
        if ($this->hasHtmlTags($content)) {
            return $content;
        }

        // Convert plain text to HTML
        return $this->toSafeHtml($content);
    }

    /**
     * Check if content has HTML tags.
     */
    protected function hasHtmlTags(string $content): bool
    {
        // Check for common block/inline tags
        return (bool) preg_match('/<(p|ul|ol|br|strong|em|h[1-6]|div|span|a|blockquote)[>\s\/]/i', $content);
    }

    /**
     * Convert plain text to safe HTML.
     * 
     * @param string $text Plain text input
     * @return string HTML output
     */
    public function toSafeHtml(string $text): string
    {
        if (empty($text)) {
            return '';
        }

        // Escape HTML entities first
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Convert double newlines to paragraph breaks
        $paragraphs = preg_split('/\n\s*\n/', $escaped);

        $html = '';
        foreach ($paragraphs as $para) {
            // Convert single newlines to <br>
            $para = str_replace("\n", '<br>', trim($para));
            if ($para !== '') {
                $html .= '<p>' . $para . '</p>';
            }
        }

        return $html ?: '<p></p>';
    }
}
