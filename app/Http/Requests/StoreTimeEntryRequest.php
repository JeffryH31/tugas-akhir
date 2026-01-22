<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTimeEntryRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'duration' => ['required', 'integer', 'min:1', 'max:1440'], // max 24 hours in minutes
            'description' => ['nullable', 'string', 'max:500'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date', 'after:started_at'],
            'is_billable' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'duration.required' => 'Duration is required.',
            'duration.min' => 'Duration must be at least 1 minute.',
            'duration.max' => 'Duration cannot exceed 24 hours.',
            'ended_at.after' => 'End time must be after start time.',
        ];
    }
}
