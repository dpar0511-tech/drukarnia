<?php

namespace App\Services\Communication;

class EmailParserService
{
    public function __construct(protected HtmlPurifier $purifier) {}

    /**
     * Parse and clean email content.
     */
    public function parseContent(string $rawHtml): string
    {
        // 1. Purify HTML to remove potential XSS
        $purified = $this->purifier->purify($rawHtml);

        // 2. Remove quoted history (replies)
        $cleaned = $this->removeQuotedHistory($purified);

        // 3. Normalize whitespace but keep newlines
        $cleaned = preg_replace('/[ \t]+/', ' ', $cleaned); // Normalize spaces/tabs
        $cleaned = preg_replace('/\n\s*\n/', "\n\n", $cleaned); // Normalize multiple newlines

        return trim($cleaned);
    }

    /**
     * Remove quoted history (replies) from email content.
     */
    protected function removeQuotedHistory(string $content): string
    {
        // Patterns for common email reply markers
        $patterns = [
            '/(?i)(---+\s*Original Message\s*---+.*)/s',
            '/(?i)(From:.*Sent:.*To:.*Subject:.*)/s',
            '/(?i)(On\s+.*wrote:.*)/s',
            '/(?i)(W\s+dniu\s+.*napisał\(a\):.*)/s',
            '/<blockquote[^>]*>.*?<\/blockquote>/is',
            '/(?i)(---------- Forwarded message ----------.*)/s',
        ];

        return preg_replace($patterns, '', $content);
    }

    /**
     * Clean and normalize email subject.
     */
    public function extractSubject(?string $subject): string
    {
        if (! $subject) {
            return '(Brak tematu)';
        }

        // Remove common prefixes like Re:, Fwd:, Odp:
        return trim(preg_replace('/^(Re:|Fwd:|Odp:|Aw:)\s+/i', '', $subject));
    }

    /**
     * Extract order number from subject if present.
     */
    public function extractOrderNumber(?string $subject): ?string
    {
        if (! $subject) {
            return null;
        }

        if (preg_match('/DRK-\d{4}-\d{5}/i', $subject, $matches)) {
            return strtoupper($matches[0]);
        }

        return null;
    }
}
