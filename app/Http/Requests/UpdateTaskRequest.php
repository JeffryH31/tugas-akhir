<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'status_id' => ['nullable', 'exists:statuses,id'],
            'priority_id' => ['nullable', 'exists:priorities,id'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'time_estimate' => ['nullable', 'integer', 'min:0', 'max:525600'],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['exists:users,id'],
            'label_ids' => ['nullable', 'array'],
            'label_ids.*' => ['exists:labels,id'],
        ];
    }
}
