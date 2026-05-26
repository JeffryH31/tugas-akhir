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
            'duration' => ['nullable', 'integer', 'min:1', 'max:1440', 'required_without_all:started_at,ended_at'], // max 24 hours in minutes
            'started_at' => ['nullable', 'date', 'required_with:ended_at'],
            'ended_at' => ['nullable', 'date', 'after:started_at', 'required_with:started_at'],
            'is_billable' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'duration.required_without_all' => 'Duration or start/end time is required.',
            'duration.min' => 'Duration must be at least 1 minute.',
            'duration.max' => 'Duration cannot exceed 24 hours.',
            'started_at.required_with' => 'Start time is required when end time is provided.',
            'ended_at.required_with' => 'End time is required when start time is provided.',
            'ended_at.after' => 'End time must be after start time.',
        ];
    }
}
