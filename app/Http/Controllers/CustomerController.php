<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Accounting\CharAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    /**
     * الحصول على الحساب الأب للعملاء.
     *
     * الحساب النظامي:
     * 1103 - العملاء
     */
    private function getParentAccount()
    {
        return CharAccount::where('system_key', 'customers')
            ->where('isPostable', 0)
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

            if (!str_starts_with((string) $code, $prefix)) {
                continue;
            }

            $suffix = substr(
                (string) $code,
                strlen($prefix)
            );

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
     * صفحة العملاء.
     */
    public function index()
    {
        $customers = Customer::with('account')
            ->orderBy('CustomersID', 'DESC')
            ->get();

        $parent = $this->getParentAccount();

        return view(
            'setting.customers.index',
            [
                'customers' => $customers,
                'hasParent' => (bool) $parent,
            ]
        );
    }

    /**
     * قائمة العملاء AJAX.
     */
    public function list(Request $request)
    {
        $query = Customer::with('account')
            ->orderBy('CustomersID', 'DESC');

        if ($request->filled('search_name')) {

            $searchName = trim(
                $request->search_name
            );

            $query->where(
                'CustomersName2',
                'like',
                '%' . $searchName . '%'
            );
        }

        if ($request->filled('search_phone')) {

            $searchPhone = trim(
                $request->search_phone
            );

            $query->where(
                'CusPhone',
                'like',
                '%' . $searchPhone . '%'
            );
        }

        if ($request->filled('search_code')) {

            $searchCode = trim(
                $request->search_code
            );

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

        $customers = $query->get();

        $html = view(
            'setting.customers.table',
            compact('customers')
        )->render();

        return response()->json([
            'success' => true,
            'html' => $html,
        ]);
    }

    /**
     * الحصول على بيانات عميل.
     */
    public function show(int|string $id)
    {
        $customer = Customer::with('account')
            ->find($id);

        if (!$customer) {

            return response()->json([
                'success' => false,
                'message' => 'العميل غير موجود',
            ], 404);
        }

        return response()->json([
            'success' => true,

            'customer' => [
                'CustomersID' =>
                    $customer->CustomersID,

                'CustomersName2' =>
                    $customer->CustomersName2,

                'CusPhone' =>
                    $customer->CusPhone,

                'CusAddress' =>
                    $customer->CusAddress,

                'CusIsStopeed' =>
                    $customer->CusIsStopeed ?? 0,

                'accountID' =>
                    $customer->accountID,

                'accountCode' =>
                    $customer->account
                        ? $customer->account->accCode
                        : null,

                'account' =>
                    $customer->account,
            ],
        ]);
    }

    /**
     * إضافة عميل جديد.
     */
    public function store(Request $request)
    {
        $request->validate([
            'CustomersName2' => [
                'required',
                'string',
                'max:255',
            ],
        ], [
            'CustomersName2.required' =>
                'اسم العميل مطلوب',

            'CustomersName2.string' =>
                'اسم العميل غير صحيح',

            'CustomersName2.max' =>
                'اسم العميل يجب ألا يتجاوز 255 حرفاً',
        ]);

        try {

            $result = DB::transaction(
                function () use ($request) {

                    /*
                     * 1. الحصول على الحساب الأب
                     *    عن طريق system_key
                     */
                    $parent =
                        $this->getParentAccount();

                    if (!$parent) {

                        throw new \Exception(
                            'لم يتم العثور على الحساب الأب للعملاء.'
                        );
                    }

                    /*
                     * 2. توليد رقم الحساب التحليلي
                     */
                    $nextCode =
                        $this->generateNextChildCode(
                            $parent
                        );

                    /*
                     * 3. إنشاء الحساب التحليلي
                     */
                    $account =
                        CharAccount::create([
                            'accParent' =>
                                $parent->accountID,

                            'accTypeID' =>
                                $parent->accTypeID,

                            'accCode' =>
                                $nextCode,

                            'accName' =>
                                $request->CustomersName2,

                            'nature' =>
                                $parent->nature,

                            'accLevel' =>
                                $parent->accLevel + 1,

                            'IsActive' => 1,

                            'isPostable' => 1,

                            'is_system' => 0,

                            'system_key' => null,
                        ]);

                    /*
                     * 4. إنشاء العميل وربطه بالحساب
                     */
                    $customer =
                        Customer::create([
                            'CustomersName2' =>
                                $request->CustomersName2,

                            'accountID' =>
                                $account->accountID,

                            'CusPhone' =>
                                $request->CusPhone,

                            'CusAddress' =>
                                $request->CusAddress,

                            'CusIsStopeed' =>
                                $request->input(
                                    'CusIsStopeed',
                                    0
                                ),
                        ]);

                    return [
                        'customer' =>
                            $customer,

                        'account' =>
                            $account,
                    ];
                }
            );

            return response()->json([
                'success' => true,

                'message' =>
                    'تم إضافة العميل وربطه بالحساب التحليلي بنجاح.',

                'customer' => [
                    'CustomersID' =>
                        $result['customer']->CustomersID,

                    'CustomersName2' =>
                        $result['customer']->CustomersName2,

                    'CusPhone' =>
                        $result['customer']->CusPhone,

                    'CusAddress' =>
                        $result['customer']->CusAddress,

                    'CusIsStopeed' =>
                        $result['customer']->CusIsStopeed,

                    'accountID' =>
                        $result['account']->accountID,

                    'accountCode' =>
                        $result['account']->accCode,
                ],
            ], 200);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * تعديل العميل.
     */
    public function update(
        Request $request,
        int|string $id
    ) {
        $request->validate([
            'CustomersName2' => [
                'required',
                'string',
                'max:255',
            ],
        ], [
            'CustomersName2.required' =>
                'اسم العميل مطلوب',

            'CustomersName2.string' =>
                'اسم العميل غير صحيح',

            'CustomersName2.max' =>
                'اسم العميل يجب ألا يتجاوز 255 حرفاً',
        ]);

        try {

            $customer =
                Customer::find($id);

            if (!$customer) {

                return response()->json([
                    'success' => false,
                    'message' => 'العميل غير موجود',
                ], 404);
            }

            /*
             * Observer سيتولى مزامنة
             * اسم وحالة الحساب التحليلي.
             */
            $customer->CustomersName2 =
                $request->CustomersName2;

            $customer->CusPhone =
                $request->CusPhone;

            $customer->CusAddress =
                $request->CusAddress;

            $customer->CusIsStopeed =
                $request->input(
                    'CusIsStopeed',
                    0
                );

            $customer->save();

            return response()->json([
                'success' => true,
                'message' =>
                    'تم تحديث بيانات العميل بنجاح.',
            ], 200);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * حذف العميل.
     */
    public function destroy(int|string $id)
    {
        try {

            DB::transaction(function () use ($id) {

                $customer =
                    Customer::findOrFail($id);

                $accountID =
                    $customer->accountID;

                /*
                 * حذف العميل أولاً.
                 */
                $customer->delete();

                /*
                 * التعامل مع الحساب التحليلي المرتبط.
                 */
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

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' =>
                    'لا يمكن حذف العميل لوجود حركات أو فواتير مرتبطة به',
            ], 422);
        }
    }
}