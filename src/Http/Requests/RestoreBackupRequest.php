<?php

namespace LucaPon\StatamicContentBackup\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RestoreBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'regex:/^[a-zA-Z0-9_\-\.]+\.zip$/'],
        ];
    }
}
