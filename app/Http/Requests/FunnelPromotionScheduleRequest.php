<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FunnelPromotionScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('scheduled_for') && $this->input('scheduled_for') === '') {
            $this->merge(['scheduled_for' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'scheduled_for' => ['required', 'date', 'after:now'],
            'timezone' => ['nullable', 'string', 'max:64'],
        ];
    }
}
