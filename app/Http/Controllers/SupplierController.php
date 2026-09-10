<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
        // =====================================================
        // عرض قائمة الموردين
        // =====================================================

        public function index()
        {
            $suppliers =
                Supplier::orderBy(
                    'suplierID',
                    'DESC'
                )->get();

            return view(
                'setting.suppliers.index',
                compact(
                    'suppliers'
                )
            );
        }

    // =====================================================
    // جلب مورد للتعديل
    // =====================================================

    public function show(
        int|string $id
    ) {
        $supplier =
            Supplier::find($id);

        if (!$supplier) {

            return response()->json([
                'success' => false,
                'message' => 'المورد غير موجود',
            ], 404);
        }

        $statusVal =
            $supplier->supStoped ?? 0;

        $accountCode =
            2101000 +
            (int) $supplier->suplierID;

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
                    $statusVal,

                'accountID' =>
                    $supplier->accountID,

                'accountCode' =>
                    $accountCode,
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
            'supName' =>
                'required|string|max:255',

        ], [

            'supName.required' =>
                'اسم المورد مطلوب',

            'supName.string' =>
                'اسم المورد يجب أن يكون نصًا',

        ]);

        $status =
            $request->input(
                'supStoped',
                0
            );

        $supplier =
            Supplier::create([

                'supName' =>
                    $request->supName,

                'supPhone' =>
                    $request->supPhone,

                'supArea' =>
                    $request->supArea,

                'supStoped' =>
                    $status,
            ]);

        $accountCode =
            2101000 +
            (int) $supplier->suplierID;

        return response()->json([

            'success' =>
                true,

            'message' =>
                'تم إضافة المورد بنجاح',

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
                    $accountCode,
            ],
        ]);
    }

    // =====================================================
    // تحديث المورد
    // =====================================================

    public function update(
        Request $request,
        int|string $id
    ) {
        $request->validate([
            'supName' =>
                'required|string|max:255',

        ], [

            'supName.required' =>
                'اسم المورد مطلوب',

            'supName.string' =>
                'اسم المورد يجب أن يكون نصًا',

        ]);

        $supplier =
            Supplier::find($id);

        if (!$supplier) {

            return response()->json([
                'success' => false,
                'message' => 'المورد غير موجود',
            ], 404);
        }

        $status =
            $request->input(
                'supStoped',
                0
            );

        $supplier->update([

            'supName' =>
                $request->supName,

            'supPhone' =>
                $request->supPhone,

            'supArea' =>
                $request->supArea,

            'supStoped' =>
                $status,
        ]);

        $accountCode =
            2101000 +
            (int) $supplier->suplierID;

        return response()->json([

            'success' =>
                true,

            'message' =>
                'تم تحديث بيانات المورد بنجاح',

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
                    $accountCode,
            ],
        ]);
    }

    // =====================================================
    // حذف المورد
    // =====================================================

    public function destroy(
        int|string $id
    ) {
        $supplier =
            Supplier::find($id);

        if (!$supplier) {

            return response()->json([
                'success' => false,
                'message' => 'المورد غير موجود',
            ], 404);
        }

        try {

            $supplierID =
                $supplier->suplierID;

            $supplier->delete();

            return response()->json([

                'success' =>
                    true,

                'message' =>
                    'تم حذف المورد بنجاح',

                'supplierID' =>
                    $supplierID,
            ]);

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
            Supplier::orderBy(
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

            if (
                is_numeric(
                    $searchCode
                )
            ) {

                $query->whereRaw(
                    "CAST(2101000 + suplierID AS CHAR) LIKE ?",
                    [
                        '%' .
                        $searchCode .
                        '%'
                    ]
                );

            } else {

                $query->whereRaw(
                    '1 = 0'
                );
            }
        }

        // =================================================
        // جلب الموردين
        // =================================================

        $suppliers =
            $query->get();

        // =================================================
        // إنشاء الجدول والصفوف بواسطة Blade
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