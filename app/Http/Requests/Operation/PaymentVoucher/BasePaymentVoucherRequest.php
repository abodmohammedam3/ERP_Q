<?php

namespace App\Http\Requests\Operation\PaymentVoucher;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Validator;

/**
 * قاعدة مشتركة لطلبات سندات الصرف.
 *
 * ملاحظات تصميمية:
 *   - عمود characcount لفحص قابلية الترحيل اسمه `isPostable` (camelCase).
 *     تحقق من الاسم الفعلي:
 *       SHOW COLUMNS FROM characcount LIKE '%ostable%';
 *   - `authorize()` تُعيد true لأن التفويض يتم في Controller
 *     عبر Permissions::authorize (نمط المشروع الموحّد).
 */
abstract class BasePaymentVoucherRequest extends FormRequest
{
    /**
     * الحد الأقصى لعمق شجرة الحسابات في فحص fallback.
     * الشجرة النموذجية في ERP: 5-7 مستويات.
     * 20 حماية استباقية ضد الدوران اللانهائي.
     */
    private const MAX_TREE_DEPTH = 20;

    /**
     * علم يُسجَّل مرة واحدة فقط عند فشل CTE.
     * يمنع امتلاء laravel.log بمئات التحذيرات على MySQL 5.7.
     */
    private static bool $cteWarningLogged = false;

    public function authorize(): bool
    {
        return true;
    }

    // =========================================================
    // RULES
    // =========================================================

    protected function commonRules(): array
    {
        return [
            'voucherDate' => 'nullable|date',

            'beneficiaryAccountID' => 'required|integer',
            'paymentAccountID'     => 'required|integer',

            'coinsID' => 'required|integer|exists:coins,coinsID',

            'amount'       => 'required|numeric|min:0.01|max:999999999999.99',
            'exchangeRate' => 'required|numeric|gt:0',

            'paymentMethod' => 'required|integer|in:2,3',
            'notes'         => 'nullable|string|max:1000',
        ];
    }

    protected static function commonMessages(): array
    {
        return [
            'coinsID.required'         => 'يجب اختيار العملة.',
            'coinsID.exists'           => 'العملة المختارة غير موجودة.',
            'amount.required'          => 'يجب إدخال المبلغ.',
            'amount.numeric'           => 'المبلغ يجب أن يكون رقماً.',
            'amount.min'               => 'المبلغ يجب أن يكون أكبر من صفر.',
            'amount.max'               => 'المبلغ يتجاوز الحد المسموح.',
            'exchangeRate.required'    => 'يجب إدخال سعر الصرف.',
            'exchangeRate.numeric'     => 'سعر الصرف يجب أن يكون رقماً.',
            'exchangeRate.gt'          => 'سعر الصرف يجب أن يكون أكبر من صفر.',
            'paymentMethod.required'   => 'يجب اختيار طريقة الدفع.',
            'paymentMethod.in'         => 'طريقة الدفع يجب أن تكون 2 (نقد) أو 3 (بنك).',
            'voucherDate.date'         => 'صيغة تاريخ السند غير صحيحة.',
            'voucherNumber.regex'      => 'رقم السند يجب أن يحتوي أرقاماً فقط.',
            'voucherNumber.unique'     => 'رقم السند مستخدم مسبقاً.',
            'voucherNumber.max'        => 'رقم السند طويل جداً.',
            'voucherNumber.prohibited' => 'لا يمكن تغيير رقم السند بعد الإنشاء.',
        ];
    }

    public static function commonAttributes(): array
    {
        return [
            'voucherNumber'        => 'رقم السند',
            'voucherDate'          => 'تاريخ السند',
            'beneficiaryAccountID' => 'حساب المستفيد',
            'paymentAccountID'     => 'حساب الدفع',
            'coinsID'              => 'العملة',
            'amount'               => 'المبلغ',
            'exchangeRate'         => 'سعر الصرف',
            'paymentMethod'        => 'طريقة الدفع',
            'notes'                => 'الملاحظات',
        ];
    }

    public function attributes(): array
    {
        return self::commonAttributes();
    }

    // =========================================================
    // COMMON VALIDATION — مع Early Exit
    // =========================================================

    protected function runCommonValidation(Validator $v): void
    {
        $this->validateAccounts($v);

        /* ★ Early exit: لا فحوصات إضافية إذا فشلت الحسابات */
        if ($v->errors()->has('beneficiaryAccountID')
            || $v->errors()->has('paymentAccountID')) {
            return;
        }

        $this->validateVoucherDate($v);
        $this->validateHierarchy($v);
    }

    /**
     * استعلام واحد للحسابين — يتحقق من الوجود + isPostable.
     */
    protected function validateAccounts(Validator $v): void
    {
        $beneficiary = (int) $this->input('beneficiaryAccountID');
        $payment     = (int) $this->input('paymentAccountID');

        if ($beneficiary <= 0 || $payment <= 0) {
            return;
        }

        if ($beneficiary === $payment) {
            $v->errors()->add(
                'beneficiaryAccountID',
                'لا يمكن أن يكون المستفيد نفس حساب الدفع.'
            );
            return;
        }

        $accounts = DB::table('characcount')
            ->whereIn('accountID', [$beneficiary, $payment])
            ->get(['accountID', 'isPostable'])
            ->keyBy('accountID');

        if (! $accounts->has($beneficiary)) {
            $v->errors()->add('beneficiaryAccountID', 'حساب المستفيد غير موجود.');
        } elseif ((int) $accounts[$beneficiary]->isPostable !== 1) {
            $v->errors()->add(
                'beneficiaryAccountID',
                'حساب المستفيد غير قابل للترحيل (isPostable = 0).'
            );
        }

        if (! $accounts->has($payment)) {
            $v->errors()->add('paymentAccountID', 'حساب الدفع غير موجود.');
        } elseif ((int) $accounts[$payment]->isPostable !== 1) {
            $v->errors()->add(
                'paymentAccountID',
                'حساب الدفع غير قابل للترحيل (isPostable = 0).'
            );
        }
    }

    protected function validateVoucherDate(Validator $v): void
    {
        $date = $this->input('voucherDate');
        if (! $date) return;

        try {
            $parsed = Carbon::parse($date, config('app.timezone'))->startOfDay();
        } catch (\Throwable $e) {
            $v->errors()->add('voucherDate', 'صيغة التاريخ غير صحيحة.');
            return;
        }

        if ($parsed->greaterThan(now()->startOfDay())) {
            $v->errors()->add(
                'voucherDate',
                'لا يمكن أن يكون تاريخ السند في المستقبل.'
            );
        }
    }

    // =========================================================
    // HIERARCHY — CTE واحد للفحصين
    // =========================================================

    /**
     * فحص الشجرة باستعلام واحد:
     *   - هل payment سليل beneficiary؟
     *   - هل beneficiary سليل payment؟
     */
    protected function validateHierarchy(Validator $v): void
    {
        $beneficiary = (int) $this->input('beneficiaryAccountID');
        $payment     = (int) $this->input('paymentAccountID');

        if ($beneficiary <= 0 || $payment <= 0 || $beneficiary === $payment) {
            return;
        }

        try {
            /* ★ CTE واحد يجلب سلسلتَي الأجداد ويقارن دفعة واحدة */
            $rows = DB::select("
                WITH RECURSIVE
                b_anc AS (
                    SELECT accountID FROM characcount WHERE accountID = ?
                    UNION ALL
                    SELECT c.accountID
                      FROM characcount c
                      JOIN b_anc a ON c.accountID = a.accParent
                ),
                p_anc AS (
                    SELECT accountID FROM characcount WHERE accountID = ?
                    UNION ALL
                    SELECT c.accountID
                      FROM characcount c
                      JOIN p_anc a ON c.accountID = a.accParent
                )
                SELECT
                    EXISTS(SELECT 1 FROM b_anc WHERE accountID = ?) AS p_in_b,
                    EXISTS(SELECT 1 FROM p_anc WHERE accountID = ?) AS b_in_p
            ", [$beneficiary, $payment, $payment, $beneficiary]);

            if (! empty($rows)) {
                if ((int) $rows[0]->p_in_b === 1) {
                    $v->errors()->add(
                        'paymentAccountID',
                        'حساب الدفع لا يمكن أن يكون فرعاً للمستفيد.'
                    );
                }
                if ((int) $rows[0]->b_in_p === 1) {
                    $v->errors()->add(
                        'beneficiaryAccountID',
                        'المستفيد لا يمكن أن يكون فرعاً لحساب الدفع.'
                    );
                }
            }

        } catch (\Throwable $e) {
            $this->logCteFailureOnce($e);

            if ($this->isAncestorFallback($beneficiary, $payment)) {
                $v->errors()->add(
                    'paymentAccountID',
                    'حساب الدفع لا يمكن أن يكون فرعاً للمستفيد.'
                );
            }
            if ($this->isAncestorFallback($payment, $beneficiary)) {
                $v->errors()->add(
                    'beneficiaryAccountID',
                    'المستفيد لا يمكن أن يكون فرعاً لحساب الدفع.'
                );
            }
        }
    }

    /**
     * تسجيل تحذير واحد فقط عند فشل CTE (لتفادي امتلاء الـ log).
     */
    private function logCteFailureOnce(\Throwable $e): void
    {
        if (self::$cteWarningLogged) return;
        self::$cteWarningLogged = true;

        Log::warning('[PaymentVoucherRequest] Recursive CTE unavailable, using PHP fallback.', [
            'error'   => $e->getMessage(),
            'user_id' => auth()->id(),
            'note'    => 'Further CTE failures will not be logged.',
        ]);
    }

    /**
     * Fallback للشجرة — عند فشل CTE (MySQL 5.7).
     * يستخدم MAX_TREE_DEPTH لحماية من الدوران.
     */
    protected function isAncestorFallback(int $possibleAncestor, int $accountID): bool
    {
        $current = $accountID;
        $guard = 0;

        while ($current > 0 && $guard++ < self::MAX_TREE_DEPTH) {
            $parent = (int) DB::table('characcount')
                ->where('accountID', $current)
                ->value('accParent');

            if ($parent <= 0) break;
            if ($parent === $possibleAncestor) return true;

            $current = $parent;
        }

        if ($guard >= self::MAX_TREE_DEPTH) {
            Log::warning('[PaymentVoucherRequest] Tree depth exceeded MAX_TREE_DEPTH.', [
                'accountID' => $accountID,
                'max_depth' => self::MAX_TREE_DEPTH,
                'user_id'   => auth()->id(),
            ]);
        }

        return false;
    }
}