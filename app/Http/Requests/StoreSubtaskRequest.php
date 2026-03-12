<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubtaskRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'status_id' => [
                'nullable',
                'exists:statuses,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $status = \App\Models\Status::find($value);
                        if ($status && $status->applies_to === 'tasks') {
                            $fail('This status can only be used for tasks, not subtasks.');
                        }
                    }
                },
            ],
            'priority_level' => ['nullable', 'integer', 'in:1,2,3,4'],
            'task_id' => ['required', 'exists:tasks,id'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'time_estimate' => ['nullable', 'integer', 'min:0', 'max:525600'], // max 1 year in minutes
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['exists:users,id'],
            'label_ids' => ['nullable', 'array'],
            'label_ids.*' => ['exists:labels,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Subtask name is required.',
            'name.max' => 'Subtask name must not exceed 255 characters.',
            'due_date.after_or_equal' => 'Due date must be after or equal to start date.',
            'status_id.exists' => 'Selected status does not exist.',
            'priority_level.in' => 'Selected priority does not exist.',
            'task_id.required' => 'Task ID is required.',
            'task_id.exists' => 'Parent task does not exist.',
            'time_estimate.max' => 'Time estimate cannot exceed 1 year.',
        ];
    }
}
