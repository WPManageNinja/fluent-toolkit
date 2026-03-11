<?php

namespace FluentToolkit\AI\Common;

class MarkdownRenderer
{
    public function render(string $content): string
    {
        if ($content === '') {
            return '';
        }

        $html = (new Parsedown([]))
            ->setBreaksEnabled(true)
            ->setUrlsLinked(false)
            ->text($content);

        return wp_kses_post($html);
    }
}
