<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon' => ['nullable', 'string', 'max:50'],
            'folder_id' => ['nullable', 'exists:folders,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'List name is required.',
            'name.max' => 'List name must not exceed 255 characters.',
            'folder_id.exists' => 'Folder does not exist.',
        ];
    }
}
