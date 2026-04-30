<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }


    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:10000'],
            'parent_id' => ['nullable', 'exists:comments,id'],
            'subtask_id' => ['nullable', 'exists:subtasks,id'],
            'mentions' => ['nullable', 'array'],
            'mentions.*' => ['exists:users,id'],
        ];
    }


    public function messages(): array
    {
        return [
            'content.required' => 'Comment content is required.',
            'content.max' => 'Comment must not exceed 10000 characters.',
            'parent_id.exists' => 'Parent comment does not exist.',
        ];
    }
}
