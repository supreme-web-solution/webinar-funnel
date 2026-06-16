<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FunnelPromotionTopicGenerateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'count' => ['nullable', 'integer', 'min:5', 'max:30'],
            'context' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
