<?php
// app/Modules/GradeTracking/Http/Requests/ArchiveSubmissionRequest.php

namespace App\Modules\GradeTracking\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ArchiveSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSecretariat() ?? false;
    }

    public function rules(): array
    {
        return [
            'hash' => ['required', 'string'],
        ];
    }
}