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
            'webinar_duration_seconds' => ['nullable', 'integer', 'min:1', 'max:86400'],
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
            'offers' => ['nullable', 'array', 'max:30'],
            'offers.*.title' => ['required_with:offers', 'string', 'max:200'],
            'offers.*.description' => ['nullable', 'string', 'max:1000'],
            'offers.*.cta_label' => ['required_with:offers', 'string', 'max:120'],
            'offers.*.cta_url' => ['required_with:offers', 'url', 'max:2048'],
            'offers.*.placement' => ['required_with:offers', 'in:chat,pinned,popup'],
            'offers.*.timing_seconds' => ['required_with:offers', 'integer', 'min:0', 'max:86400'],
            'offers.*.enabled' => ['nullable', 'boolean'],
            'exit_popup_enabled' => ['nullable', 'boolean'],
            'exit_popup_show_close' => ['nullable', 'boolean'],
            'exit_popup_title' => ['nullable', 'string', 'max:200'],
            'exit_popup_description' => ['nullable', 'string', 'max:1000'],
            'exit_popup_cta_label' => ['nullable', 'string', 'max:120'],
            'exit_popup_cta_url' => ['nullable', 'url', 'max:2048'],
            'integration_account_ids' => ['nullable', 'array'],
            'integration_account_ids.*' => [
                'integer',
                Rule::exists('integration_accounts', 'id')->where('user_id', $this->user()->id),
            ],
            'integration_configs' => ['nullable', 'array'],
            'integration_configs.*' => ['nullable', 'array'],
            'traffic_ai_reply_enabled' => ['nullable', 'boolean'],
            'traffic_ai_link_override' => ['nullable', 'url', 'max:2048'],
            'traffic_ai_extra_context' => ['nullable', 'string', 'max:5000'],
            'traffic_ai_max_replies_per_day' => ['nullable', 'integer', 'min:1', 'max:500'],
            'traffic_ai_social_account_ids' => ['nullable', 'array'],
            'traffic_ai_social_account_ids.reddit' => [
                'nullable',
                'integer',
                Rule::exists('social_accounts', 'id')->where('user_id', $this->user()->id)->where('platform', 'reddit'),
            ],
            'traffic_ai_social_account_ids.youtube' => [
                'nullable',
                'integer',
                Rule::exists('social_accounts', 'id')->where('user_id', $this->user()->id)->where('platform', 'youtube'),
            ],
            'traffic_ai_social_account_ids.twitter' => [
                'nullable',
                'integer',
                Rule::exists('social_accounts', 'id')->where('user_id', $this->user()->id)->where('platform', 'twitter'),
            ],
        ];
    }
}
