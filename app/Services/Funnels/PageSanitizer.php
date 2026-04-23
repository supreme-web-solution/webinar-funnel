<?php

namespace App\Services\Funnels;

class PageSanitizer
{
    /**
     * Strip potentially dangerous fields/scripts from GrapesJS payload.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public function sanitize(array $schema): array
    {
        $sanitized = $this->walk($schema);

        if (isset($sanitized['scripts'])) {
            unset($sanitized['scripts']);
        }

        return $sanitized;
    }

    /**
     * @param  mixed  $value
     * @return mixed
     */
    private function walk(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $result = [];

        foreach ($value as $key => $item) {
            if (is_string($key) && str_contains(strtolower($key), 'script')) {
                continue;
            }

            if (is_string($item) && str_contains(strtolower($item), '<script')) {
                continue;
            }

            $result[$key] = $this->walk($item);
        }

        return $result;
    }
}
