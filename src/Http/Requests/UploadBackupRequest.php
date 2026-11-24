<?php

namespace LucaPon\StatamicContentBackup\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file'],
            'resumableIdentifier' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_\-]+$/'],
            'resumableChunkNumber' => ['required', 'integer', 'min:1'],
            'resumableTotalChunks' => ['required', 'integer', 'gte:resumableChunkNumber'],
            'resumableChunkSize' => ['required', 'integer', 'min:1'],
            'resumableTotalSize' => ['required', 'integer', 'min:1'],
        ];
    }
}
