<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Bank;
use App\Models\Accounting\CharAccount;
use App\Models\Accounting\Coin;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class BankController extends Controller
{
    // =====================================================
    // الحصول على حساب "البنوك" الأب
    // =====================================================
    private function getParentAccount()
    {
        $parent = CharAccount::where('system_key', 'banks')
            ->where('isPostable', 0)
            ->first();

        if ($parent) {
            return $parent;
        }

        throw new \Exception(
            'لم يتم العثور على الحساب الأب للبنوك في دليل الحسابات. يرجى إنشاؤه أولاً.'
        );
    }

    // =====================================================
    // توليد رقم الحساب الفرعي التالي
    // =====================================================
    private function generateNextChildCode($parent)
    {
        $childLevel = $parent->accLevel + 1;

        $segmentLength =
            ($childLevel === 2)
                ? 1
                : 2;

        $children = CharAccount::where(
                'accParent',
                $parent->accountID
            )
            ->orderBy('accCode')
            ->get(['accCode']);

        $maxSequence = 0;

        $parentCode =
            (string) $parent->accCode;

        foreach ($children as $child) {

            $childCode =
                (string) $child->accCode;

            if (
                !str_starts_with(
                    $childCode,
                    $parentCode
                )
            ) {
                continue;
            }

            $suffix = substr(
                $childCode,
                strlen($parentCode)
            );

            if (
                strlen($suffix) !== $segmentLength ||
                !ctype_digit($suffix)
            ) {
                continue;
            }

            $seq =
                (int) $suffix;

            if ($seq > $maxSequence) {
                $maxSequence = $seq;
            }
        }

        $nextSequence =
            $maxSequence + 1;

        $maxAllowed =
            ($segmentLength === 1)
                ? 9
                : 99;

        if ($nextSequence > $maxAllowed) {

            throw new \Exception(
                'تم الوصول إلى الحد الأقصى للحسابات الفرعية في هذا المستوى'
            );
        }

        $segment = str_pad(
            (string) $nextSequence,
            $segmentLength,
            '0',
            STR_PAD_LEFT
        );

        return $parentCode . $segment;
    }

    // =====================================================
    // عرض الصفحة
    // =====================================================
    public function index()
    {
        try {

            $this->getParentAccount();

            $hasParent = true;

        } catch (\Exception $e) {

            $hasParent = false;
        }

        $banks = Bank::with([
                'account',
                'coin'
            ])
            ->orderBy('bankID', 'asc')
            ->get();

        $coins = Coin::where(
                'is_active',
                1
            )
            ->orderBy(
                'coinsID',
                'asc'
            )
            ->get();

        return view(
            'setting.accounting.banks.index',
            compact(
                'banks',
                'coins',
                'hasParent'
            )
        );
    }

    // =====================================================
    // جلب البيانات
    // =====================================================
    public function list()
    {
        $banks = Bank::with([
                'account',
                'coin'
            ])
            ->orderBy(
                'bankID',
                'asc'
            )
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $banks,
        ]);
    }

    // =====================================================
    // رقم الحساب التالي
    // =====================================================
    public function getNextCode()
    {
        try {

            $parent =
                $this->getParentAccount();

            $nextCode =
                $this->generateNextChildCode(
                    $parent
                );

            return response()->json([
                'success' => true,
                'code'    => $nextCode,
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
// =====================================================
// إضافة بنك
// =====================================================
public function store(Request $request)
{
    // =====================================================
    // التأكد من وجود الحساب الأب
    // =====================================================
    try {

        $parent = $this->getParentAccount();

    } catch (\Exception $e) {

        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 422);
    }

    // =====================================================
    // التحقق من البيانات
    // =====================================================
    $validator = Validator::make(
        $request->all(),
        [
            'bankName' => [
                'required',
                'string',
                'max:255',
                'unique:banks,bankName',
            ],

            'coinsID' => [
                'required',
                'exists:coins,coinsID',
            ],

            'accountNumber' =>
                'nullable|string|max:50',

            'is_active' =>
                'nullable|boolean',
        ],
        [
            'bankName.required' =>
                'اسم البنك مطلوب',

            'bankName.unique' =>
                'اسم البنك موجود بالفعل',

            'coinsID.required' =>
                'عملة البنك مطلوبة',

            'coinsID.exists' =>
                'العملة المختارة غير صحيحة',
        ]
    );

    if ($validator->fails()) {

        return response()->json([
            'success' => false,
            'message' =>
                $validator->errors()->first(),

            'errors' =>
                $validator->errors(),
        ], 422);
    }

    // =====================================================
    // تنفيذ الإضافة
    // =====================================================
    try {

        DB::transaction(function () use (
            $request,
            $parent
        ) {

            // =================================================
            // توليد رقم الحساب التحليلي التالي
            // =================================================
            $nextCode =
                $this->generateNextChildCode(
                    $parent
                );

            // =================================================
            // إنشاء الحساب التحليلي للبنك
            // =================================================
            $account = CharAccount::create([

                'accTypeID' =>
                    $parent->accTypeID,

                'accCode' =>
                    $nextCode,

                'accParent' =>
                    $parent->accountID,

                'accName' =>
                    $request->bankName,

                'nature' =>
                    $parent->nature,

                'accLevel' =>
                    $parent->accLevel + 1,

                'IsActive' =>
                    $request->boolean(
                        'is_active',
                        true
                    ) ? 1 : 0,

                'isPostable' =>
                    1,

                'is_system' =>
                    0,

                'system_key' =>
                    null,
            ]);

            // =================================================
            // إنشاء سجل البنك وربطه بالحساب
            // =================================================
            Bank::create([

                'bankName' =>
                    $request->bankName,

                'accountID' =>
                    $account->accountID,

                'coinsID' =>
                    $request->coinsID,

                'accountNumber' =>
                    $request->accountNumber ?: null,

                'is_active' =>
                    $request->boolean(
                        'is_active',
                        true
                    ),
            ]);

            // =================================================
            // BankObserver يعمل تلقائياً
            // لمزامنة اسم البنك وحالته مع الحساب
            // =================================================
        });

        return response()->json([
            'success' => true,
            'message' =>
                'تم إضافة البنك بنجاح',
        ]);

    } catch (\Exception $e) {

        return response()->json([
            'success' => false,
            'message' =>
                $e->getMessage(),
        ], 422);
    }
}


// =====================================================
// تعديل بنك
// =====================================================
public function update(
    Request $request,
    $id
) {
    try {

        $bank =
            Bank::findOrFail($id);

        // =================================================
        // التحقق من البيانات
        // =================================================
        $validator = Validator::make(
            $request->all(),
            [
                'bankName' => [
                    'required',
                    'string',
                    'max:255',

                    Rule::unique(
                        'banks',
                        'bankName'
                    )->ignore(
                        $bank->bankID,
                        'bankID'
                    ),
                ],

                'coinsID' => [
                    'required',
                    'exists:coins,coinsID',
                ],

                'accountNumber' =>
                    'nullable|string|max:50',

                'is_active' =>
                    'nullable|boolean',
            ],
            [
                'bankName.required' =>
                    'اسم البنك مطلوب',

                'bankName.unique' =>
                    'اسم البنك موجود بالفعل',

                'coinsID.required' =>
                    'عملة البنك مطلوبة',

                'coinsID.exists' =>
                    'العملة المختارة غير صحيحة',
            ]
        );

        if ($validator->fails()) {

            return response()->json([
                'success' => false,
                'message' =>
                    $validator->errors()->first(),

                'errors' =>
                    $validator->errors(),
            ], 422);
        }

        // =================================================
        // تنفيذ التعديل
        // =================================================
        DB::transaction(function () use (
            $request,
            $bank
        ) {

            // =================================================
            // تحديث بيانات البنك فقط
            // =================================================
            //
            // BankObserver يتولى مزامنة:
            // - اسم البنك
            // - حالة البنك
            //
            $bank->update([

                'bankName' =>
                    $request->bankName,

                'coinsID' =>
                    $request->coinsID,

                'accountNumber' =>
                    $request->accountNumber ?: null,

                'is_active' =>
                    $request->boolean(
                        'is_active',
                        true
                    ) ? 1 : 0,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' =>
                'تم تحديث البنك بنجاح',
        ]);

    } catch (
        \Illuminate\Database\Eloquent\ModelNotFoundException $e
    ) {

        return response()->json([
            'success' => false,
            'message' =>
                'البنك غير موجود',
        ], 404);

    } catch (\Exception $e) {

        return response()->json([
            'success' => false,
            'message' =>
                'حدث خطأ أثناء التحديث: ' .
                $e->getMessage(),
        ], 422);
    }
}

    // =====================================================
    // حذف بنك
    // =====================================================
    public function destroy($id)
    {
        try {

            $bank =
                Bank::findOrFail($id);

            $accountId =
                $bank->accountID;

            DB::transaction(function () use (
                $bank,
                $accountId
            ) {

                // =================================================
                // حذف البنك
                // =================================================
                $bank->delete();

                // =================================================
                // التعامل مع الحساب المرتبط
                // =================================================
                $account =
                    CharAccount::find(
                        $accountId
                    );

                if ($account) {

                    $hasChildren =
                        CharAccount::where(
                            'accParent',
                            $accountId
                        )->exists();

                    if (!$hasChildren) {

                        $account->delete();

                    } else {

                        $account->update([
                            'IsActive' => 0,
                        ]);
                    }
                }
            });

            return response()->json([
                'success' => true,
                'message' =>
                    'تم حذف البنك بنجاح',
            ]);

        } catch (
            \Illuminate\Database\Eloquent\ModelNotFoundException $e
        ) {

            return response()->json([
                'success' => false,
                'message' =>
                    'البنك غير موجود',
            ], 404);

        } catch (
            \Illuminate\Database\QueryException $e
        ) {

            if ($e->getCode() == 23000) {

                return response()->json([
                    'success' => false,
                    'message' =>
                        'لا يمكن حذف البنك لأن الحساب المرتبط به مستخدم في مكان آخر.',
                ], 422);
            }

            return response()->json([
                'success' => false,
                'message' =>
                    'حدث خطأ أثناء الحذف: ' .
                    $e->getMessage(),
            ], 422);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' =>
                    'حدث خطأ أثناء الحذف: ' .
                    $e->getMessage(),
            ], 422);
        }
    }

    // =====================================================
    // تبديل الحالة
    // =====================================================
    public function toggleStatus($id)
    {
        try {

            $bank =
                Bank::findOrFail($id);

            $newStatus =
                !$bank->is_active;

            DB::transaction(function () use (
                $bank,
                $newStatus
            ) {

                // =================================================
                // تحديث حالة البنك فقط
                // =================================================
                //
                // BankObserver سيتولى مزامنة حالة الحساب.
                //
                $bank->update([
                    'is_active' =>
                        $newStatus,
                ]);
            });

            return response()->json([
                'success' => true,

                'message' =>
                    $newStatus
                        ? 'تم التفعيل بنجاح'
                        : 'تم التعطيل بنجاح',

                'data' =>
                    $bank->fresh('account'),
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' =>
                    'حدث خطأ: ' .
                    $e->getMessage(),
            ], 422);
        }
    }
}