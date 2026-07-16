<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class Html
{
    public static function sanitizeCampaign(string $html): string
    {
        $config = (new HtmlSanitizerConfig)
            ->allowSafeElements()
            ->allowRelativeLinks()
            ->allowRelativeMedias()
            ->allowAttribute('class', '*')
            ->allowAttribute('style', '*')
            ->allowAttribute('target', ['a'])
            ->allowAttribute('rel', ['a'])
            ->allowAttribute('src', ['img'])
            ->allowAttribute('alt', ['img'])
            ->allowAttribute('width', ['img', 'table', 'td', 'th'])
            ->allowAttribute('height', ['img', 'table', 'td', 'th'])
            ->allowAttribute('border', ['table'])
            ->allowAttribute('cellpadding', ['table'])
            ->allowAttribute('cellspacing', ['table'])
            ->allowAttribute('align', ['table', 'td', 'th', 'img', 'p', 'div'])
            ->withMaxInputLength(2_000_000);

        return (new HtmlSanitizer($config))->sanitize($html);
    }
}
