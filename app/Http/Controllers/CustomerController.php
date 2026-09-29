<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Accounting\CharAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;

class CustomerController extends Controller
{
    /**
     * كاش للحساب الأب (بدون قفل).
     */
    private ?CharAccount $parentAccount = null;

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * الحصول على الحساب الأب للعملاء (بدون قفل).
     *
     * الحساب النظامي:
     * 1103 - العملاء
     */
    private function getParentAccount(): ?CharAccount
    {
        return $this->parentAccount ??= CharAccount::where('system_key', 'customers')
            ->where('isPostable', 0)
            ->first();
    }

    /**
     * الحصول على الحساب الأب مع قفل الصف (داخل transaction فقط).
     * يمنع race condition عند توليد الأكواد المتزامنة.
     */
    private function getParentAccountForUpdate(): ?CharAccount
    {
        return CharAccount::where('system_key', 'customers')
            ->where('isPostable', 0)
            ->lockForUpdate()
            ->first();
    }

    /**
     * توليد رقم الحساب التحليلي التالي.
     *
     * مثال:
     * 110301
     * 110302
     * 110303
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
     * بناء استعلام العملاء مع الفلاتر.
     */
    private function buildFilteredQuery(Request $request)
    {
        $query = Customer::with('account');

        if ($request->filled('search_name')) {

            $searchName = trim($request->search_name);

            $query->where(
                'CustomersName2',
                'like',
                '%' . $searchName . '%'
            );
        }

        if ($request->filled('search_phone')) {

            $searchPhone = trim($request->search_phone);

            $query->where(
                'CusPhone',
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
     * تحويل العميل إلى مصفوفة JSON موحّدة.
     * يضمن أن is_active دائمًا int (0 أو 1).
     */
    private function customerToArray(Customer $customer): array
    {
        return [
            'CustomersID'    => $customer->CustomersID,
            'CustomersName2' => $customer->CustomersName2,
            'CusPhone'       => $customer->CusPhone,
            'CusAddress'     => $customer->CusAddress,
            'is_active'      => (int) $customer->is_active,
            'accountID'      => $customer->accountID,
            'accountCode'    => $customer->account->accCode ?? null,
        ];
    }

    // ============================================================
    // Actions
    // ============================================================

    /**
     * صفحة العملاء.
     */
    public function index(): View
    {
        $customers = Customer::with('account')
            ->orderBy('CustomersID', 'DESC')
            ->get();

        return view('setting.customers.index', [
            'customers' => $customers,
            'hasParent' => (bool) $this->getParentAccount(),
        ]);
    }

    /**
     * قائمة العملاء AJAX.
     */
    public function list(Request $request): JsonResponse
    {
        $customers = $this->buildFilteredQuery($request)
            ->orderBy('CustomersID', 'DESC')
            ->get();

        $html = view(
            'setting.customers.table',
            compact('customers')
        )->render();

        return response()->json([
            'success' => true,
            'html'    => $html,
        ]);
    }

    /**
     * الحصول على بيانات عميل.
     */
    public function show(int|string $id): JsonResponse
    {
        $customer = Customer::with('account')->find($id);

        if (!$customer) {

            return response()->json([
                'success' => false,
                'message' => 'العميل غير موجود',
            ], 404);
        }

        return response()->json([
            'success'  => true,
            'customer' => $this->customerToArray($customer),
        ]);
    }

    /**
     * إضافة عميل جديد.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'CustomersName2' => ['required', 'string', 'max:255'],
            'CusPhone'       => ['nullable', 'string', 'max:50'],
            'CusAddress'     => ['nullable', 'string', 'max:500'],
            'is_active'      => ['sometimes', 'boolean'],
        ], [
            'CustomersName2.required' => 'اسم العميل مطلوب',
            'CustomersName2.string'   => 'اسم العميل غير صحيح',
            'CustomersName2.max'      => 'اسم العميل يجب ألا يتجاوز 255 حرفاً',
            'CusPhone.max'            => 'رقم الهاتف طويل جداً',
            'CusAddress.max'          => 'العنوان طويل جداً',
        ]);

        try {

            $result = DB::transaction(function () use ($validated, $request) {

                $parent = $this->getParentAccountForUpdate();

                if (!$parent) {
                    throw new \DomainException(
                        'لم يتم العثور على الحساب الأب للعملاء.'
                    );
                }

                $nextCode = $this->generateNextChildCode($parent);

                // ✅ تحديد حالة العميل الجديد
                // - إذا أُرسل is_active صريحاً، نأخذ قيمته.
                // - إذا لم يُرسل (checkbox غير مفعّل)، الافتراضي = نشط (1).
                $newIsActive = $request->has('is_active')
                    ? ($request->boolean('is_active') ? 1 : 0)
                    : 1;

                $account = CharAccount::create([
                    'accParent'  => $parent->accountID,
                    'accTypeID'  => $parent->accTypeID,
                    'accCode'    => $nextCode,
                    'accName'    => $validated['CustomersName2'],
                    'nature'     => $parent->nature,
                    'accLevel'   => $parent->accLevel + 1,
                    'IsActive'   => $newIsActive,
                    'isPostable' => 1,
                    'is_system'  => 0,
                    'system_key' => null,
                ]);

                $customer = Customer::create([
                    'CustomersName2' => $validated['CustomersName2'],
                    'accountID'      => $account->accountID,
                    'CusPhone'       => $validated['CusPhone'] ?? null,
                    'CusAddress'     => $validated['CusAddress'] ?? null,
                    'is_active'      => $newIsActive,
                ]);

                return compact('customer', 'account');
            });

            return response()->json([
                'success'  => true,
                'message'  => 'تم إضافة العميل وربطه بالحساب التحليلي بنجاح.',
                'customer' => [
                    'CustomersID'    => $result['customer']->CustomersID,
                    'CustomersName2' => $result['customer']->CustomersName2,
                    'CusPhone'       => $result['customer']->CusPhone,
                    'CusAddress'     => $result['customer']->CusAddress,
                    'is_active'      => (int) $result['customer']->is_active,
                    'accountID'      => $result['account']->accountID,
                    'accountCode'    => $result['account']->accCode,
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
                'message' => 'حدث خطأ أثناء حفظ العميل',
            ], 500);
        }
    }

    /**
     * تعديل العميل.
     */
    public function update(Request $request, int|string $id): JsonResponse
    {
        $validated = $request->validate([
            'CustomersName2' => ['required', 'string', 'max:255'],
            'CusPhone'       => ['nullable', 'string', 'max:50'],
            'CusAddress'     => ['nullable', 'string', 'max:500'],
            'is_active'      => ['sometimes', 'boolean'],
        ], [
            'CustomersName2.required' => 'اسم العميل مطلوب',
            'CustomersName2.string'   => 'اسم العميل غير صحيح',
            'CustomersName2.max'      => 'اسم العميل يجب ألا يتجاوز 255 حرفاً',
            'CusPhone.max'            => 'رقم الهاتف طويل جداً',
            'CusAddress.max'          => 'العنوان طويل جداً',
        ]);

        try {

            $customer = Customer::find($id);

            if (!$customer) {

                return response()->json([
                    'success' => false,
                    'message' => 'العميل غير موجود',
                ], 404);
            }

            $customer->CustomersName2 = $validated['CustomersName2'];
            $customer->CusPhone       = $validated['CusPhone'] ?? null;
            $customer->CusAddress     = $validated['CusAddress'] ?? null;

            // ✅ الإصلاح الجوهري:
            // إذا لم يُرسل is_active (checkbox غير مفعّل) → القيمة = 0.
            // إذا أُرسل → نأخذ قيمته الحقيقية.
            $customer->is_active = $request->boolean('is_active') ? 1 : 0;

            $customer->save();

            return response()->json([
                'success'  => true,
                'message'  => 'تم تحديث بيانات العميل بنجاح.',
                'customer' => $this->customerToArray(
                    $customer->fresh(['account'])
                ),
            ], 200);

        } catch (\Throwable $e) {

            report($e);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث العميل',
            ], 500);
        }
    }

    /**
     * حذف العميل.
     */
    public function destroy(int|string $id): JsonResponse
    {
        try {

            DB::transaction(function () use ($id) {

                $customer = Customer::findOrFail($id);

                $accountID = $customer->accountID;

                $customer->delete();

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
                'message' => 'تم حذف العميل بنجاح.',
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {

            return response()->json([
                'success' => false,
                'message' => 'العميل غير موجود',
            ], 404);

        } catch (\Illuminate\Database\QueryException $e) {

            if ($e->getCode() === '23000') {

                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن حذف العميل لوجود حركات أو فواتير مرتبطة به',
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
     * بحث العملاء (JSON) — لنظام Unified Lookup.
     */
    public function search(Request $request): JsonResponse
    {
        $search = trim($request->input('search', ''));

        $query = Customer::with('account')
            ->orderBy('CustomersID', 'desc')
            ->limit(50);

        if ($search !== '') {

            $query->where(function ($q) use ($search) {

                $q->where('CustomersName2', 'like', "%{$search}%")
                  ->orWhere('CusPhone', 'like', "%{$search}%")
                  ->orWhereHas(
                      'account',
                      function ($aq) use ($search) {
                          $aq->where('accCode', 'like', "%{$search}%");
                      }
                  );
            });
        }

        $customers = $query->get()->map(function ($c) {
            return [
                'CustomersID'    => $c->CustomersID,
                'CustomersName2' => $c->CustomersName2,
                'CusPhone'       => $c->CusPhone,
                'accountID'      => $c->accountID,
                'accountCode'    => $c->account->accCode ?? null,
                'is_active'      => (int) $c->is_active,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $customers,
        ]);
    }


    /**
     * تقرير الطباعة (صفحة HTML كاملة).
     */
    public function print(Request $request): View
    {
        $customers = $this->buildFilteredQuery($request)
            ->orderBy('CustomersID', 'asc')
            ->get();

        // الفلاتر المطبقة
        $filters = [];

        if ($request->filled('search_name')) {
            $filters['اسم العميل'] = $request->search_name;
        }
        if ($request->filled('search_phone')) {
            $filters['رقم الهاتف'] = $request->search_phone;
        }
        if ($request->filled('search_code')) {
            $filters['رقم الحساب'] = $request->search_code;
        }

        return view('setting.customers.print', [
            'customers'         => $customers,
            'totalCustomers'    => $customers->count(),
            'activeCustomers'   => $customers->where('is_active', 1)->count(),
            'inactiveCustomers' => $customers->where('is_active', 0)->count(),
            'printDate'         => now()->format('Y-m-d H:i'),
            'filters'           => $filters,
        ]);
    }
}