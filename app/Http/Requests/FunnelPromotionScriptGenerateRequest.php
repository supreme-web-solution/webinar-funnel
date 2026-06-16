<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FunnelPromotionScriptGenerateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'topic' => ['required', 'string', 'max:255'],
            'generation_context' => ['nullable', 'array'],
            'generation_context.context' => ['nullable', 'string', 'max:2000'],
            'cta_url' => ['nullable', 'url', 'max:2048'],
            'cta_label' => ['nullable', 'string', 'max:120'],
        ];
    }
}
