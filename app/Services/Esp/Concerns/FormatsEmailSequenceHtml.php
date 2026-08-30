<?php

namespace App\Services\Esp\Concerns;

trait FormatsEmailSequenceHtml
{
    protected function bodyToHtml(string $body): string
    {
        $escaped = e($body);

        return '<div style="font-family:system-ui,sans-serif;line-height:1.6;color:#111;">'
            .nl2br($escaped)
            .'</div>';
    }
}
