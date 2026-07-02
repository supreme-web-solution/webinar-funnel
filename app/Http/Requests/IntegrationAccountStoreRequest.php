<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IntegrationAccountStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $provider = $this->input('provider');

        return [
            'provider' => ['required', Rule::in([
                'mailchimp', 'getresponse', 'convertkit', 'activecampaign',
                'sendinblue', 'brevo', 'generic_webhook',
            ])],
            'name' => ['required', 'string', 'max:120'],
            'credentials' => ['required', 'array'],

            // ── Shared API key (ESP providers + optional webhook auth token) ─
            'credentials.api_key' => [
                Rule::requiredIf(in_array($provider, ['mailchimp', 'getresponse', 'sendinblue', 'brevo', 'activecampaign'], true)),
                'nullable', 'string', 'max:500',
            ],

            // ── Mailchimp ────────────────────────────────────────────────────
            'credentials.audience_id' => [
                Rule::requiredIf($provider === 'mailchimp'),
                'nullable', 'string', 'max:200',
            ],

            // ── GetResponse ──────────────────────────────────────────────────
            'credentials.campaign_id' => [
                Rule::requiredIf($provider === 'getresponse'),
                'nullable', 'string', 'max:200',
            ],

            // ── ConvertKit ───────────────────────────────────────────────────
            'credentials.api_secret' => [
                Rule::requiredIf($provider === 'convertkit'),
                'nullable', 'string', 'max:500',
            ],
            'credentials.form_id' => [
                Rule::requiredIf($provider === 'convertkit'),
                'nullable', 'string', 'max:200',
            ],

            // ── ActiveCampaign ───────────────────────────────────────────────
            'credentials.api_url' => [
                Rule::requiredIf($provider === 'activecampaign'),
                'nullable', 'url', 'max:500',
            ],
            'credentials.list_id' => [
                Rule::requiredIf(in_array($provider, ['activecampaign', 'sendinblue', 'brevo'], true)),
                'nullable', 'string', 'max:200',
            ],

            // ── Generic Webhook ──────────────────────────────────────────────
            'credentials.webhook_url' => [
                Rule::requiredIf($provider === 'generic_webhook'),
                'nullable', 'url', 'max:500',
            ],

            'config' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
