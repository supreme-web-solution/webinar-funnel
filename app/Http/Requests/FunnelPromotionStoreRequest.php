<?php

namespace App\Http\Requests;

use App\Models\FunnelPromotionPost;
use App\Services\Promotion\PromotionPlatformCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class FunnelPromotionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['cta_url', 'title', 'topic', 'text_body', 'email_subject', 'email_body'];
        $normalized = [];

        foreach ($nullable as $key) {
            if ($this->has($key) && $this->input($key) === '') {
                $normalized[$key] = null;
            }
        }

        if ($this->has('auto_generate')) {
            $normalized['auto_generate'] = filter_var($this->input('auto_generate'), FILTER_VALIDATE_BOOLEAN);
        }

        // Cast boolean generation_context sub-fields coming from JS (true/false or 'true'/'false')
        $ctx = $this->input('generation_context', []);
        if (is_array($ctx)) {
            foreach (['include_text', 'include_image'] as $boolKey) {
                if (array_key_exists($boolKey, $ctx)) {
                    $ctx[$boolKey] = filter_var($ctx[$boolKey], FILTER_VALIDATE_BOOLEAN);
                }
            }
            $normalized['generation_context'] = $ctx;
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    public function rules(): array
    {
        $catalog = app(PromotionPlatformCatalog::class);
        $supported = $catalog->supportedPlatforms();
        $isEmail = $this->input('content_type') === FunnelPromotionPost::TYPE_EMAIL;

        return [
            'title' => ['nullable', 'string', 'max:200'],
            'topic' => ['required', 'string', 'max:255'],
            'content_type' => [
                'required',
                Rule::in([
                    FunnelPromotionPost::TYPE_TEXT,
                    FunnelPromotionPost::TYPE_IMAGE,
                    FunnelPromotionPost::TYPE_VIDEO,
                    FunnelPromotionPost::TYPE_EMAIL,
                ]),
            ],
            'platforms' => [
                Rule::requiredIf(! $isEmail),
                'array',
                $isEmail ? 'max:0' : 'min:1',
            ],
            'platforms.*' => ['string', Rule::in($supported)],
            'publish_mode' => ['required', Rule::in([FunnelPromotionPost::MODE_APPROVE_FIRST, FunnelPromotionPost::MODE_AUTO_PUBLISH])],
            'cta_url' => ['nullable', 'url', 'max:2048'],
            'cta_label' => ['nullable', 'string', 'max:120'],
            'text_body' => ['nullable', 'string', 'max:25000'],
            'email_subject' => ['nullable', 'string', 'max:255'],
            'email_body' => ['nullable', 'string', 'max:25000'],
            'hashtags' => ['nullable', 'array', 'max:20'],
            'hashtags.*' => ['string', 'max:60'],
            'generation_context'                => ['nullable', 'array'],
            'generation_context.context'        => ['nullable', 'string', 'max:2000'],
            'generation_context.goal'           => ['nullable', 'string', 'max:255'],
            'generation_context.voice'          => ['nullable', 'string', 'max:120'],
            'generation_context.volume'         => ['nullable', 'integer', 'min:1', 'max:100'],
            'generation_context.include_text'   => ['nullable', 'boolean'],
            'generation_context.include_image'  => ['nullable', 'boolean'],
            'generation_context.avatar_id'      => ['nullable', 'string', 'max:120'],
            'generation_context.voice_id'       => ['nullable', 'string', 'max:120'],
            'generation_context.email_type'     => ['nullable', 'string', Rule::in(['promotional', 'follow-up', 'newsletter'])],
            'auto_generate'                     => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('content_type') === FunnelPromotionPost::TYPE_EMAIL) {
                return;
            }

            $connected = app(PromotionPlatformCatalog::class)->connectedPlatformKeys((int) $this->user()->id);
            foreach ((array) $this->input('platforms', []) as $platform) {
                if (! is_string($platform) || ! in_array($platform, $connected, true)) {
                    $validator->errors()->add(
                        'platforms',
                        'One or more platforms are not connected. Link them in Settings → Social posting.'
                    );

                    return;
                }
            }
        });
    }
}
