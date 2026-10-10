<?php

namespace App\Http\Requests\Settings\Backup;

use Illuminate\Foundation\Http\FormRequest;

class ExportBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'format' => ['nullable', 'in:sql'],
        ];
    }

    public function messages(): array
    {
        return [
            'format.in' => 'صيغة التصدير غير مدعومة، الصيغ المتاحة: sql.',
        ];
    }
}
