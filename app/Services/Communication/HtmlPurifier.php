<?php

namespace App\Services\Communication;

class HtmlPurifier
{
    /**
     * Purify HTML content to prevent XSS.
     * For now, it's a simple wrapper. In production, consider using mews/purifier.
     */
    public function purify(string $html): string
    {
        // 1. Remove dangerous tags
        $dangerousTags = ['script', 'iframe', 'object', 'embed', 'style', 'applet', 'meta', 'link'];
        foreach ($dangerousTags as $tag) {
            $html = preg_replace('/<'.$tag.'\b[^>]*>(.*?)<\/'.$tag.'>/is', '', $html);
            $html = preg_replace('/<'.$tag.'\b[^>]*>/is', '', $html);
        }

        // 2. Remove JS event handlers
        $html = preg_replace('/on\w+="[^"]*"/is', '', $html);
        $html = preg_replace('/on\w+=\'[^\']*\'/is', '', $html);

        // 3. Remove javascript: pseudo-protocol
        $html = preg_replace('/href="javascript:[^"]*"/is', 'href="#"', $html);

        return trim($html);
    }
}
