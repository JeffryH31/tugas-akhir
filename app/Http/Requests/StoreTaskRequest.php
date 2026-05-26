<?php

namespace App\Http\Requests;

use App\Models\Status;
use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'task_id' => ['nullable', 'exists:tasks,id'],
            'status_id' => [
                'nullable',
                'exists:statuses,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $status = Status::find($value);
                        if ($status && $status->applies_to === 'subtasks') {
                            $fail('This status can only be used for subtasks, not tasks.');
                        }
                    }
                },
            ],
            'priority_level' => ['nullable', 'integer', 'in:1,2,3,4'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'time_estimate' => ['nullable', 'integer', 'min:0', 'max:525600'],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['exists:users,id'],
            'label_ids' => ['nullable', 'array'],
            'label_ids.*' => ['exists:labels,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Task name is required.',
            'name.max' => 'Task name must not exceed 255 characters.',
            'due_date.after_or_equal' => 'Due date must be after or equal to start date.',
            'status_id.exists' => 'Selected status does not exist.',
            'priority_level.in' => 'Selected priority does not exist.',
            'parent_id.exists' => 'Parent task does not exist.',
            'time_estimate.max' => 'Time estimate cannot exceed 1 year.',
        ];
    }
}
