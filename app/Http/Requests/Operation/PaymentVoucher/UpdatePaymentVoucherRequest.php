<?php

namespace App\Http\Requests\Operation\PaymentVoucher;

use Illuminate\Validation\Validator;

class UpdatePaymentVoucherRequest extends BasePaymentVoucherRequest
{
    public function rules(): array
    {
        return array_merge($this->commonRules(), [
            /*
             * `missing` بدل `prohibited`:
             *   - يرفض الطلب إذا وُجد الحقل بقيمة
             *   - يقبل null (لأن JS يرسله أحياناً)
             */
            'voucherNumber' => 'nullable|missing',
        ]);
    }

    public function messages(): array
    {
        return self::commonMessages();
    }

    public function withValidator(Validator $v): void
    {
        $v->after(function (Validator $v) {

            $id = (int) $this->route('id');

            if ($id <= 0) {
                $v->errors()->add('id', 'معرّف السند غير صالح.');
                return;
            }

            /*
             * فحص is_posted يبقى في Service (مع lockForUpdate)
             * لتفادي Race Condition بين التحقق والتنفيذ.
             */
            $this->runCommonValidation($v);
        });
    }
}