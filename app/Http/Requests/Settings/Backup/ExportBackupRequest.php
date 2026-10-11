<?php

namespace App\Http\Requests\Settings\Backup;

use Illuminate\Foundation\Http\FormRequest;

class ExportBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * قواعد التحقق — مطابقة تماماً لما يتوقعه BackupController::export()
     * - format : sql (الافتراضي) — zip مخطط له لاحقاً
     */
    public function rules(): array
    {
        return [
            'format' => [
                'nullable',
                'string',
                'in:sql',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'format.in' => 'الصيغة المدعومة حالياً هي SQL فقط.',
        ];
    }

    public function attributes(): array
    {
        return [
            'format' => 'صيغة الملف',
        ];
    }
}