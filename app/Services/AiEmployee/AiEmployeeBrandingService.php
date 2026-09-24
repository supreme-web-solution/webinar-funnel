<?php

namespace App\Services\AiEmployee;

final class AiEmployeeBrandingService
{
    /**
     * Public URL for the AI employee avatar (config path or absolute URL).
     */
    public function avatarUrl(): string
    {
        $configured = (string) config('ai_employee.avatar', '/images/ai-employee/alex.png');
        if ($configured === '') {
            return asset('images/ai-employee/alex.png');
        }

        if (str_starts_with($configured, 'http://') || str_starts_with($configured, 'https://')) {
            return $configured;
        }

        $path = ltrim($configured, '/');

        return asset($path);
    }

    /**
     * @return array{name: string, title: string, avatar_url: string}
     */
    public function employeePayload(): array
    {
        return [
            'name' => (string) config('ai_employee.name', 'Alex'),
            'title' => (string) config('ai_employee.title', 'Command Center'),
            'avatar_url' => $this->avatarUrl(),
        ];
    }
}
