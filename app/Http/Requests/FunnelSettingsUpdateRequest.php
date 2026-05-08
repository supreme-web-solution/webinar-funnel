<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FunnelSettingsUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'webinar_title' => ['nullable', 'string', 'max:200'],
            'webinar_description' => ['nullable', 'string', 'max:5000'],
            'video_url' => ['nullable', 'url', 'max:2048'],
            'webinar_cta_label' => ['nullable', 'string', 'max:120'],
            'webinar_cta_url' => ['nullable', 'url', 'max:2048'],
            'affiliate_request_link' => ['nullable', 'url', 'max:2048'],
            'jv_page' => ['nullable', 'url', 'max:2048'],
            'chat_mode' => ['required', 'in:simulated,realtime,hybrid'],
            'countdown_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'allow_replay' => ['required', 'boolean'],
            'chat_seed_messages' => ['nullable', 'array', 'max:200'],
            'chat_seed_messages.*.author' => ['required_with:chat_seed_messages', 'string', 'max:100'],
            'chat_seed_messages.*.message' => ['required_with:chat_seed_messages', 'string', 'max:1000'],
            'chat_seed_messages.*.published_at' => ['nullable', 'date'],
            'branding' => ['nullable', 'array'],
            'branding.primary' => ['nullable', 'string', 'max:20'],
            'branding.secondary' => ['nullable', 'string', 'max:20'],
            'integration_account_ids' => ['nullable', 'array'],
            'integration_account_ids.*' => [
                'integer',
                Rule::exists('integration_accounts', 'id')->where('user_id', $this->user()->id),
            ],
            'integration_configs' => ['nullable', 'array'],
            'integration_configs.*' => ['nullable', 'array'],
        ];
    }
}
