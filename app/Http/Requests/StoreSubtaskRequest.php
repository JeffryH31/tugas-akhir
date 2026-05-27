<?php

namespace App\Http\Requests;

use App\Models\Status;
use Illuminate\Foundation\Http\FormRequest;

class StoreSubtaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:subtasks,id'],
            'status_id' => [
                'nullable',
                'exists:statuses,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $status = Status::find($value);
                        if ($status && $status->applies_to === 'tasks') {
                            $fail('This status can only be used for tasks, not subtasks.');
                        }
                    }
                },
            ],
            'task_id' => ['required', 'exists:tasks,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Subtask name is required.',
            'name.max' => 'Subtask name must not exceed 255 characters.',
            'status_id.exists' => 'Selected status does not exist.',
            'task_id.required' => 'Task ID is required.',
            'task_id.exists' => 'Parent task does not exist.',
        ];
    }
}
