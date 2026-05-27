<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTimeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date', 'after:started_at'],
        ];
    }

    public function messages(): array
    {
        return [
            'started_at.required' => 'Start time is required.',
            'ended_at.required' => 'End time is required.',
            'ended_at.after' => 'End time must be after start time.',
        ];
    }
}
