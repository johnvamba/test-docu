<?php

namespace App\Support;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Documents are shared between users, so stored HTML must be safe to render in
 * someone else's browser. Everything outside the editor's schema is stripped:
 * scripts, event handlers, inline styles, iframes, images and unknown tags.
 */
class HtmlSanitizer
{
    private const ALLOWED = 'p,br,strong,b,em,i,u,s,h1,h2,h3,ul,ol,li,blockquote,code,pre,hr,a[href]';

    private ?HTMLPurifier $purifier = null;

    public function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        return trim($this->purifier()->purify($html));
    }

    /** Plain text, for list excerpts and search. */
    public static function toText(string $html, int $limit = 160): string
    {
        $text = html_entity_decode(strip_tags(preg_replace('/<\/(p|h[1-3]|li|blockquote|pre)>/i', '$0 ', $html)));
        $text = trim(preg_replace('/\s+/u', ' ', $text));

        return mb_strimwidth($text, 0, $limit, '…');
    }

    private function purifier(): HTMLPurifier
    {
        if ($this->purifier) {
            return $this->purifier;
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', self::ALLOWED);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        $config->set('Attr.AllowedFrameTargets', []);
        $config->set('AutoFormat.RemoveEmpty', false);
        $config->set('Core.Encoding', 'UTF-8');
        // Definition caching would need a writable directory; the schema is tiny, so skip it.
        $config->set('Cache.DefinitionImpl', null);

        return $this->purifier = new HTMLPurifier($config);
    }
}
