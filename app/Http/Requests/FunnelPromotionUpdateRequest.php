<?php

namespace App\Http\Requests;

use App\Models\FunnelPromotionPost;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FunnelPromotionUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['title', 'topic', 'cta_url', 'text_body', 'email_subject', 'email_body', 'last_error', 'scheduled_for'];
        $normalized = [];

        foreach ($nullable as $key) {
            if ($this->has($key) && $this->input($key) === '') {
                $normalized[$key] = null;
            }
        }

        if ($this->has('platforms') && ! is_array($this->input('platforms'))) {
            $normalized['platforms'] = [];
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'nullable', 'string', 'max:200'],
            'topic' => ['sometimes', 'nullable', 'string', 'max:255'],
            'platforms' => ['sometimes', 'array', 'min:1', 'max:3'],
            'platforms.*' => ['string', Rule::in(['twitter', 'youtube', 'reddit'])],
            'publish_mode' => ['sometimes', Rule::in([FunnelPromotionPost::MODE_APPROVE_FIRST, FunnelPromotionPost::MODE_AUTO_PUBLISH])],
            'status' => [
                'sometimes',
                Rule::in([
                    FunnelPromotionPost::STATUS_DRAFT,
                    FunnelPromotionPost::STATUS_GENERATING,
                    FunnelPromotionPost::STATUS_READY,
                    FunnelPromotionPost::STATUS_SCHEDULED,
                    FunnelPromotionPost::STATUS_PUBLISHING,
                    FunnelPromotionPost::STATUS_PUBLISHED,
                    FunnelPromotionPost::STATUS_FAILED,
                    FunnelPromotionPost::STATUS_CANCELLED,
                ]),
            ],
            'cta_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'cta_label' => ['sometimes', 'nullable', 'string', 'max:120'],
            'text_body' => ['sometimes', 'nullable', 'string', 'max:25000'],
            'email_subject' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email_body' => ['sometimes', 'nullable', 'string', 'max:25000'],
            'hashtags' => ['sometimes', 'nullable', 'array', 'max:20'],
            'hashtags.*' => ['string', 'max:60'],
            'scheduled_for' => ['sometimes', 'nullable', 'date'],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:64'],
            'last_error' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
