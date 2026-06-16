<?php

namespace App\Http\Requests;

use App\Models\FunnelPromotionPost;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FunnelPromotionGenerateAssetsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->has('types') && ! is_array($this->input('types'))) {
            $normalized['types'] = [];
        }

        if ($this->has('wait_for_video')) {
            $normalized['wait_for_video'] = filter_var($this->input('wait_for_video'), FILTER_VALIDATE_BOOLEAN);
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    public function rules(): array
    {
        return [
            'types' => ['required', 'array', 'min:1', 'max:3'],
            'types.*' => [
                'string',
                Rule::in([
                    FunnelPromotionPost::TYPE_TEXT,
                    FunnelPromotionPost::TYPE_IMAGE,
                    FunnelPromotionPost::TYPE_VIDEO,
                    FunnelPromotionPost::TYPE_EMAIL,
                ]),
            ],
            'wait_for_video' => ['nullable', 'boolean'],
        ];
    }
}
