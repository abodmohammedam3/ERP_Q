<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\Accounting\CharAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierController extends Controller
{
    // =====================================================
    // الحساب الأب للموردين
    // =====================================================

    private function getParentAccount()
    {
        return CharAccount::where('system_key', 'suppliers')
            ->where('isPostable', 0)
            ->first();
    }

    // =====================================================
    // توليد رقم الحساب التحليلي التالي
    // =====================================================

    private function generateNextChildCode(CharAccount $parent)
    {
        $prefix = $parent->accCode;

        $children = CharAccount::where(
                'accParent',
                $parent->accountID
            )
            ->where('isPostable', 1)
            ->pluck('accCode');

        $maxNumber = 0;

        foreach ($children as $code) {

            if (
                !str_starts_with(
                    (string) $code,
                    $prefix
                )
            ) {
                continue;
            }

            $suffix = substr(
                (string) $code,
                strlen($prefix)
            );

            if (
                $suffix === '' ||
                !ctype_digit($suffix)
            ) {
                continue;
            }

            $number = (int) $suffix;

            if ($number > $maxNumber) {
                $maxNumber = $number;
            }
        }

        return $prefix . str_pad(
            (string) ($maxNumber + 1),
            2,
            '0',
            STR_PAD_LEFT
        );
    }

    // =====================================================
    // عرض قائمة الموردين
    // =====================================================

    public function index()
    {
        $suppliers = Supplier::with('account')
            ->orderBy(
                'suplierID',
                'DESC'
            )
            ->get();

        $parent = $this->getParentAccount();

        return view(
            'setting.suppliers.index',
            [
                'suppliers' => $suppliers,
                'hasParent' => (bool) $parent,
            ]
        );
    }

    // =====================================================
    // جلب مورد للتعديل
    // =====================================================

    public function show(
        int|string $id
    ) {
        $supplier = Supplier::with('account')
            ->find($id);

        if (!$supplier) {

            return response()->json([
                'success' => false,
                'message' => 'المورد غير موجود',
            ], 404);
        }

        return response()->json([
            'success' => true,

            'supplier' => [
                'suplierID' =>
                    $supplier->suplierID,

                'supName' =>
                    $supplier->supName,

                'supPhone' =>
                    $supplier->supPhone,

                'supArea' =>
                    $supplier->supArea,

                'supStoped' =>
                    $supplier->supStoped ?? 0,

                'accountID' =>
                    $supplier->accountID,

                'accountCode' =>
                    $supplier->account
                        ? $supplier->account->accCode
                        : null,
            ],
        ]);
    }

    // =====================================================
    // إضافة مورد
    // =====================================================

    public function store(
        Request $request
    ) {
        $request->validate([
            'supName' => [
                'required',
                'string',
                'max:255',
            ],

        ], [

            'supName.required' =>
                'اسم المورد مطلوب',

            'supName.string' =>
                'اسم المورد يجب أن يكون نصًا',

            'supName.max' =>
                'اسم المورد يجب ألا يتجاوز 255 حرفاً',
        ]);

        try {

            $result = DB::transaction(
                function () use ($request) {

                    // =========================================
                    // العثور على الحساب الأب باستخدام system_key
                    // =========================================

                    $parent =
                        $this->getParentAccount();

                    if (!$parent) {

                        throw new \Exception(
                            'لم يتم العثور على الحساب الأب للموردين.'
                        );
                    }

                    // =========================================
                    // توليد الرقم التحليلي
                    // =========================================

                    $nextCode =
                        $this->generateNextChildCode(
                            $parent
                        );

                    // =========================================
                    // إنشاء الحساب التحليلي
                    // =========================================

                    $account =
                        CharAccount::create([

                            'accParent' =>
                                $parent->accountID,

                            'accTypeID' =>
                                $parent->accTypeID,

                            'accCode' =>
                                $nextCode,

                            'accName' =>
                                $request->supName,

                            'nature' =>
                                $parent->nature,

                            'accLevel' =>
                                $parent->accLevel + 1,

                            'IsActive' =>
                                1,

                            'isPostable' =>
                                1,

                            'is_system' =>
                                0,

                            'system_key' =>
                                null,
                        ]);

                    // =========================================
                    // إنشاء المورد وربطه بالحساب
                    // =========================================

                    $supplier =
                        Supplier::create([

                            'supName' =>
                                $request->supName,

                            'accountID' =>
                                $account->accountID,

                            'supPhone' =>
                                $request->supPhone,

                            'supArea' =>
                                $request->supArea,

                            'supStoped' =>
                                $request->input(
                                    'supStoped',
                                    0
                                ),
                        ]);

                    return [
                        'supplier' =>
                            $supplier,

                        'account' =>
                            $account,
                    ];
                }
            );

            return response()->json([

                'success' =>
                    true,

                'message' =>
                    'تم إضافة المورد وربطه بالحساب التحليلي بنجاح.',

                'supplier' => [

                    'suplierID' =>
                        $result['supplier']
                            ->suplierID,

                    'supName' =>
                        $result['supplier']
                            ->supName,

                    'supPhone' =>
                        $result['supplier']
                            ->supPhone,

                    'supArea' =>
                        $result['supplier']
                            ->supArea,

                    'supStoped' =>
                        $result['supplier']
                            ->supStoped,

                    'accountID' =>
                        $result['account']
                            ->accountID,

                    'accountCode' =>
                        $result['account']
                            ->accCode,
                ],

            ], 200);

        } catch (\Throwable $e) {

            return response()->json([

                'success' =>
                    false,

                'message' =>
                    $e->getMessage(),

            ], 500);
        }
    }

    // =====================================================
    // تحديث المورد
    // =====================================================

    public function update(
        Request $request,
        int|string $id
    ) {
        $request->validate([
            'supName' => [
                'required',
                'string',
                'max:255',
            ],

        ], [

            'supName.required' =>
                'اسم المورد مطلوب',

            'supName.string' =>
                'اسم المورد يجب أن يكون نصًا',

            'supName.max' =>
                'اسم المورد يجب ألا يتجاوز 255 حرفاً',
        ]);

        try {

            $supplier =
                Supplier::find($id);

            if (!$supplier) {

                return response()->json([
                    'success' => false,
                    'message' => 'المورد غير موجود',
                ], 404);
            }

            $supplier->supName =
                $request->supName;

            $supplier->supPhone =
                $request->supPhone;

            $supplier->supArea =
                $request->supArea;

            $supplier->supStoped =
                $request->input(
                    'supStoped',
                    0
                );

            $supplier->save();

            return response()->json([

                'success' =>
                    true,

                'message' =>
                    'تم تحديث بيانات المورد بنجاح.',

                'supplier' => [

                    'suplierID' =>
                        $supplier->suplierID,

                    'supName' =>
                        $supplier->supName,

                    'supPhone' =>
                        $supplier->supPhone,

                    'supArea' =>
                        $supplier->supArea,

                    'supStoped' =>
                        $supplier->supStoped,

                    'accountID' =>
                        $supplier->accountID,

                    'accountCode' =>
                        $supplier->account
                            ? $supplier->account->accCode
                            : null,
                ],

            ], 200);

        } catch (\Throwable $e) {

            return response()->json([

                'success' =>
                    false,

                'message' =>
                    $e->getMessage(),

            ], 500);
        }
    }

    // =====================================================
    // حذف المورد
    // =====================================================

    public function destroy(
        int|string $id
    ) {
        try {

            DB::transaction(
                function () use ($id) {

                    $supplier =
                        Supplier::findOrFail($id);

                    $accountID =
                        $supplier->accountID;

                    // =========================================
                    // حذف المورد
                    // =========================================

                    $supplier->delete();

                    // =========================================
                    // معالجة الحساب التحليلي المرتبط
                    // =========================================

                    if ($accountID) {

                        $account =
                            CharAccount::find(
                                $accountID
                            );

                        if ($account) {

                            $hasChildren =
                                CharAccount::where(
                                    'accParent',
                                    $account->accountID
                                )->exists();

                            if (!$hasChildren) {

                                $account->delete();

                            } else {

                                $account->IsActive =
                                    0;

                                $account->save();
                            }
                        }
                    }
                }
            );

            return response()->json([

                'success' =>
                    true,

                'message' =>
                    'تم حذف المورد بنجاح.',

            ], 200);

        } catch (\Throwable $e) {

            return response()->json([

                'success' =>
                    false,

                'message' =>
                    'لا يمكن حذف المورد لوجود حركات أو فواتير مرتبطة به',

            ], 422);
        }
    }

    // =====================================================
    // البحث في قائمة الموردين
    // =====================================================

    public function list(
        Request $request
    ) {
        $query =
            Supplier::with('account')
                ->orderBy(
                    'suplierID',
                    'DESC'
                );

        // =================================================
        // البحث باسم المورد
        // =================================================

        if (
            $request->filled(
                'search_name'
            )
        ) {

            $searchName =
                trim(
                    $request->search_name
                );

            $query->where(
                'supName',
                'like',
                '%' . $searchName . '%'
            );
        }

        // =================================================
        // البحث برقم الهاتف
        // =================================================

        if (
            $request->filled(
                'search_phone'
            )
        ) {

            $searchPhone =
                trim(
                    $request->search_phone
                );

            $query->where(
                'supPhone',
                'like',
                '%' . $searchPhone . '%'
            );
        }

        // =================================================
        // البحث برقم الحساب التحليلي
        // =================================================

        if (
            $request->filled(
                'search_code'
            )
        ) {

            $searchCode =
                trim(
                    $request->search_code
                );

            $query->whereHas(
                'account',
                function ($accountQuery)
                    use ($searchCode) {

                    $accountQuery->where(
                        'accCode',
                        'like',
                        '%' . $searchCode . '%'
                    );
                }
            );
        }

        // =================================================
        // جلب الموردين
        // =================================================

        $suppliers =
            $query->get();

        // =================================================
        // إنشاء الجدول بواسطة Blade
        // =================================================

        $html =
            view(
                'setting.suppliers.table',
                compact(
                    'suppliers'
                )
            )->render();

        return response()->json([

            'success' =>
                true,

            'html' =>
                $html,
        ]);
    }
}