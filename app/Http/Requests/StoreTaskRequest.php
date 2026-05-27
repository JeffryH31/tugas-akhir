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
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Task name is required.',
            'name.max' => 'Task name must not exceed 255 characters.',
            'status_id.exists' => 'Selected status does not exist.',
        ];
    }
}
