<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FunnelPageUpdateRequest extends FormRequest
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
            'page_type'          => ['required', 'in:optin,webinar'],
            'schema'             => ['required', 'array'],
            'schema.html'        => ['sometimes', 'nullable', 'string'],
            'schema.css'         => ['sometimes', 'nullable', 'string'],
            'schema.components'  => ['sometimes', 'nullable', 'array'],
        ];
    }
}
