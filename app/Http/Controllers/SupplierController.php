<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\Accounting\CharAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;

class SupplierController extends Controller
{
    /**
     * كاش للحساب الأب (بدون قفل).
     */
    private ?CharAccount $parentAccount = null;

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * الحصول على الحساب الأب للموردين (بدون قفل).
     */
    private function getParentAccount(): ?CharAccount
    {
        return $this->parentAccount ??= CharAccount::where('system_key', 'suppliers')
            ->where('isPostable', 0)
            ->first();
    }

    /**
     * الحصول على الحساب الأب مع قفل الصف (داخل transaction فقط).
     */
    private function getParentAccountForUpdate(): ?CharAccount
    {
        return CharAccount::where('system_key', 'suppliers')
            ->where('isPostable', 0)
            ->lockForUpdate()
            ->first();
    }

    /**
     * توليد رقم الحساب التحليلي التالي.
     */
    private function generateNextChildCode(CharAccount $parent): string
    {
        $prefix = $parent->accCode;

        $children = CharAccount::where('accParent', $parent->accountID)
            ->where('isPostable', 1)
            ->pluck('accCode');

        $maxNumber = 0;

        foreach ($children as $code) {

            $code = (string) $code;

            if (!str_starts_with($code, $prefix)) {
                continue;
            }

            $suffix = substr($code, strlen($prefix));

            if ($suffix === '' || !ctype_digit($suffix)) {
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

    /**
     * بناء استعلام الموردين مع الفلاتر.
     * يُستخدم في list() و print() لتجنّب التكرار.
     */
    private function buildFilteredQuery(Request $request)
    {
        $query = Supplier::with('account');

        if ($request->filled('search_name')) {

            $searchName = trim($request->search_name);

            $query->where(
                'supName',
                'like',
                '%' . $searchName . '%'
            );
        }

        if ($request->filled('search_phone')) {

            $searchPhone = trim($request->search_phone);

            $query->where(
                'supPhone',
                'like',
                '%' . $searchPhone . '%'
            );
        }

        if ($request->filled('search_code')) {

            $searchCode = trim($request->search_code);

            $query->whereHas(
                'account',
                function ($accountQuery) use ($searchCode) {

                    $accountQuery->where(
                        'accCode',
                        'like',
                        '%' . $searchCode . '%'
                    );
                }
            );
        }

        return $query;
    }

    /**
     * تحويل المورد إلى مصفوفة JSON موحّدة.
     */
    private function supplierToArray(Supplier $supplier): array
    {
        return [
            'suplierID'   => $supplier->suplierID,
            'supName'     => $supplier->supName,
            'supPhone'    => $supplier->supPhone,
            'supArea'     => $supplier->supArea,
            'is_active'   => (int) $supplier->is_active,
            'accountID'   => $supplier->accountID,
            'accountCode' => $supplier->account->accCode ?? null,
        ];
    }

    // ============================================================
    // Actions
    // ============================================================

    /**
     * صفحة الموردين.
     */
    public function index(): View
    {
        $suppliers = Supplier::with('account')
            ->orderBy('suplierID', 'DESC')
            ->get();

        return view('setting.suppliers.index', [
            'suppliers' => $suppliers,
            'hasParent' => (bool) $this->getParentAccount(),
        ]);
    }

    /**
     * قائمة الموردين AJAX.
     */
    public function list(Request $request): JsonResponse
    {
        $suppliers = $this->buildFilteredQuery($request)
            ->orderBy('suplierID', 'DESC')
            ->get();

        $html = view(
            'setting.suppliers.table',
            compact('suppliers')
        )->render();

        return response()->json([
            'success' => true,
            'html'    => $html,
        ]);
    }

    /**
     * الحصول على بيانات مورد.
     */
    public function show(int|string $id): JsonResponse
    {
        $supplier = Supplier::with('account')->find($id);

        if (!$supplier) {

            return response()->json([
                'success' => false,
                'message' => 'المورد غير موجود',
            ], 404);
        }

        return response()->json([
            'success'  => true,
            'supplier' => $this->supplierToArray($supplier),
        ]);
    }

    /**
     * إضافة مورد جديد.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supName'    => ['required', 'string', 'max:255'],
            'supPhone'   => ['nullable', 'string', 'max:50'],
            'supArea'    => ['nullable', 'string', 'max:500'],
            'is_active'  => ['nullable', 'boolean'],
        ], [
            'supName.required' => 'اسم المورد مطلوب',
            'supName.string'   => 'اسم المورد يجب أن يكون نصًا',
            'supName.max'      => 'اسم المورد يجب ألا يتجاوز 255 حرفاً',
            'supPhone.max'     => 'رقم الهاتف طويل جداً',
            'supArea.max'      => 'المنطقة طويلة جداً',
        ]);

        try {

            $result = DB::transaction(function () use ($validated, $request) {

                // 1. الحساب الأب مع قفل
                $parent = $this->getParentAccountForUpdate();

                if (!$parent) {
                    throw new \DomainException(
                        'لم يتم العثور على الحساب الأب للموردين.'
                    );
                }

                // 2. توليد الكود
                $nextCode = $this->generateNextChildCode($parent);

                // 3. إنشاء الحساب التحليلي
                $account = CharAccount::create([
                    'accParent'  => $parent->accountID,
                    'accTypeID'  => $parent->accTypeID,
                    'accCode'    => $nextCode,
                    'accName'    => $validated['supName'],
                    'nature'     => $parent->nature,
                    'accLevel'   => $parent->accLevel + 1,
                    'IsActive'   => 1,
                    'isPostable' => 1,
                    'is_system'  => 0,
                    'system_key' => null,
                ]);

                // 4. إنشاء المورد (افتراضي: نشط)
                $supplier = Supplier::create([
                    'supName'   => $validated['supName'],
                    'accountID' => $account->accountID,
                    'supPhone'  => $validated['supPhone'] ?? null,
                    'supArea'   => $validated['supArea'] ?? null,
                    'is_active' => $request->boolean('is_active', true) ? 1 : 0,
                ]);

                return compact('supplier', 'account');
            });

            return response()->json([
                'success'  => true,
                'message'  => 'تم إضافة المورد وربطه بالحساب التحليلي بنجاح.',
                'supplier' => [
                    'suplierID'   => $result['supplier']->suplierID,
                    'supName'     => $result['supplier']->supName,
                    'supPhone'    => $result['supplier']->supPhone,
                    'supArea'     => $result['supplier']->supArea,
                    'is_active'   => (int) $result['supplier']->is_active,
                    'accountID'   => $result['account']->accountID,
                    'accountCode' => $result['account']->accCode,
                ],
            ], 200);

        } catch (\DomainException $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);

        } catch (\Throwable $e) {

            report($e);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حفظ المورد',
            ], 500);
        }
    }

    /**
     * تحديث المورد.
     */
    public function update(Request $request, int|string $id): JsonResponse
    {
        $validated = $request->validate([
            'supName'    => ['required', 'string', 'max:255'],
            'supPhone'   => ['nullable', 'string', 'max:50'],
            'supArea'    => ['nullable', 'string', 'max:500'],
            'is_active'  => ['nullable', 'boolean'],
        ], [
            'supName.required' => 'اسم المورد مطلوب',
            'supName.string'   => 'اسم المورد يجب أن يكون نصًا',
            'supName.max'      => 'اسم المورد يجب ألا يتجاوز 255 حرفاً',
            'supPhone.max'     => 'رقم الهاتف طويل جداً',
            'supArea.max'      => 'المنطقة طويلة جداً',
        ]);

        try {

            $supplier = Supplier::find($id);

            if (!$supplier) {

                return response()->json([
                    'success' => false,
                    'message' => 'المورد غير موجود',
                ], 404);
            }

            $supplier->supName  = $validated['supName'];
            $supplier->supPhone = $validated['supPhone'] ?? null;
            $supplier->supArea  = $validated['supArea'] ?? null;

            /*
             * احترام الحالة القادمة (0 أو 1).
             * إذا لم تُرسل، نحافظ على القيمة الحالية.
             */
            $supplier->is_active = $request->has('is_active')
                ? ($request->boolean('is_active') ? 1 : 0)
                : (int) $supplier->is_active;

            $supplier->save();

            return response()->json([
                'success'  => true,
                'message'  => 'تم تحديث بيانات المورد بنجاح.',
                'supplier' => $this->supplierToArray(
                    $supplier->fresh(['account'])
                ),
            ], 200);

        } catch (\Throwable $e) {

            report($e);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث المورد',
            ], 500);
        }
    }

    /**
     * حذف المورد.
     */
    public function destroy(int|string $id): JsonResponse
    {
        try {

            DB::transaction(function () use ($id) {

                $supplier = Supplier::findOrFail($id);

                $accountID = $supplier->accountID;

                // حذف المورد
                $supplier->delete();

                // معالجة الحساب التحليلي
                if ($accountID) {

                    $account = CharAccount::find($accountID);

                    if ($account) {

                        $hasChildren = CharAccount::where(
                            'accParent',
                            $account->accountID
                        )->exists();

                        if (!$hasChildren) {
                            $account->delete();
                        } else {
                            $account->IsActive = 0;
                            $account->save();
                        }
                    }
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'تم حذف المورد بنجاح.',
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {

            return response()->json([
                'success' => false,
                'message' => 'المورد غير موجود',
            ], 404);

        } catch (\Illuminate\Database\QueryException $e) {

            if ($e->getCode() === '23000') {

                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن حذف المورد لوجود حركات أو فواتير مرتبطة به',
                ], 422);
            }

            report($e);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في قاعدة البيانات',
            ], 500);

        } catch (\Throwable $e) {

            report($e);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء الحذف',
            ], 500);
        }
    }

    /**
     * بحث الموردين (JSON) — لشاشات العمليات.
     */
    public function search(Request $request): JsonResponse
    {
        $search = trim($request->input('search', ''));

        $query = Supplier::with('account')
            ->where('is_active', 1)   // ✅ الموردون النشطون فقط
            ->orderBy('suplierID', 'DESC');

        if ($search !== '') {

            $query->where(function ($q) use ($search) {

                $q->where('supName', 'like', "%{$search}%")
                  ->orWhere('supPhone', 'like', "%{$search}%")
                  ->orWhereHas(
                      'account',
                      function ($aq) use ($search) {
                          $aq->where('accCode', 'like', "%{$search}%");
                      }
                  );
            });
        }

        $suppliers = $query->limit(50)->get()->map(function ($s) {
            return [
                'suplierID'   => $s->suplierID,
                'supName'     => $s->supName,
                'accountID'   => $s->accountID,
                'accountCode' => $s->account->accCode ?? null,
                'is_active'   => (int) $s->is_active,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $suppliers,
        ]);
    }

    /**
     * تقرير الطباعة (صفحة HTML كاملة).
     */
    public function print(Request $request): View
    {
        $suppliers = $this->buildFilteredQuery($request)
            ->orderBy('suplierID', 'asc')
            ->get();

        // الفلاتر المطبقة
        $filters = [];

        if ($request->filled('search_name')) {
            $filters['اسم المورد'] = $request->search_name;
        }
        if ($request->filled('search_phone')) {
            $filters['رقم الهاتف'] = $request->search_phone;
        }
        if ($request->filled('search_code')) {
            $filters['رقم الحساب'] = $request->search_code;
        }

        return view('setting.suppliers.print', [
            'suppliers'         => $suppliers,
            'totalSuppliers'    => $suppliers->count(),
            'activeSuppliers'   => $suppliers->where('is_active', 1)->count(),
            'inactiveSuppliers' => $suppliers->where('is_active', 0)->count(),
            'printDate'         => now()->format('Y-m-d H:i'),
            'filters'           => $filters,
        ]);
    }
}