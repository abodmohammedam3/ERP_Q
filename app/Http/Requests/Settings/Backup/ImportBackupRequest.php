<?php

namespace App\Http\Requests\Settings\Backup;

use Illuminate\Foundation\Http\FormRequest;

class ImportBackupRequest extends FormRequest
{
    /**
     * الوصول مسموح (يمكنك لاحقاً تقييده بالصلاحيات).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * قواعد التحقق — مطابقة تماماً لما يتوقعه BackupController::import()
     * - backup_file  : الملف المرفوع (SQL فقط)
     * - confirmation : كلمة "استبدال" للتأكيد
     */
    public function rules(): array
    {
        return [
            'backup_file' => [
                'required',
                'file',
                'max:512000',          // 500 ميجا بالكيلوبايت
                // ⚠️ فحص الامتداد (لا MIME): نسخ mysqldump محتواها text/plain
                // فيخمّنها Laravel كـ txt — لذا mimes:sql كانت سترفض النسخ
                // الحقيقية. extensions:sql يفحص اسم الملف الأصلي فقط.
                // + لا تقبل txt: ملف غير SQL كان سيحذف البيانات ثم يفشل.
                'extensions:sql',
            ],
            'confirmation' => [
                'required',
                'string',
                'in:استبدال',
            ],
        ];
    }

    /**
     * رسائل الخطأ بالعربية.
     */
    public function messages(): array
    {
        return [
            'backup_file.required'  => 'يجب اختيار ملف النسخة الاحتياطية.',
            'backup_file.file'      => 'الملف المرفوع غير صالح.',
            'backup_file.max'       => 'حجم الملف يتجاوز 500 ميجا.',
            'backup_file.mimes'     => 'الملف يجب أن يكون بصيغة .sql فقط.',
            'backup_file.extensions' => 'الملف يجب أن يكون بصيغة .sql فقط.',
            'confirmation.required' => 'يجب تأكيد الاستيراد.',
            'confirmation.in'       => 'يجب كتابة كلمة: استبدال',
        ];
    }

    /**
     * أسماء الحقول بالعربية.
     */
    public function attributes(): array
    {
        return [
            'backup_file'  => 'ملف النسخة الاحتياطية',
            'confirmation' => 'تأكيد الاستيراد',
        ];
    }
}