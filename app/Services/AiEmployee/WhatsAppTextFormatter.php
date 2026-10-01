<?php

namespace App\Services\AiEmployee;

class WhatsAppTextFormatter
{
    /**
     * WhatsApp does not render markdown links. Turn them into plain https URLs it can open.
     */
    public function format(string $text): string
    {
        $text = preg_replace_callback(
            '/\[([^\]]+)\]\(([^)\s]+)\)/',
            function (array $match): string {
                $label = trim($match[1]);
                $url = $this->absolute($match[2]);
                if ($label === '' || strcasecmp($label, $url) === 0) {
                    return $url;
                }

                return $label.': '.$url;
            },
            $text,
        ) ?? $text;

        $formatted = preg_replace_callback(
            '#(?<![\w:/])(/(?:campaigns|funnels|traffic|command-center|growth|dashboard|webinars|settings)(?:/[^\s)<]*)?)#',
            fn (array $match): string => $this->absolute($match[1]),
            $text,
        );

        return is_string($formatted) ? $formatted : $text;
    }

    public function absolute(string $url): string
    {
        $url = trim($url);
        if (preg_match('#^https?://#i', $url) === 1) {
            return $url;
        }

        $base = rtrim((string) config('app.url'), '/');
        if (! str_starts_with($url, '/')) {
            $url = '/'.$url;
        }

        return $base.$url;
    }
}
