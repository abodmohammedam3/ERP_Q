<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Accounting\CharAccount;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    // =====================================================
    // عرض قائمة العملاء
    // =====================================================

    public function index()
    {
        $customers =
            Customer::with('account')
                ->orderBy('CustomersID', 'DESC')
                ->get();

        $accounts =
            CharAccount::where('IsActive', 1)
                ->get();

        return view(
            'setting.customers.index',
            compact('customers', 'accounts')
        );
    }

    // =====================================================
    // جلب عميل للتعديل
    // =====================================================

    public function show(
        int|string $id
    ) {
        $customer =
            Customer::with('account')
                ->find($id);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'العميل غير موجود',
            ], 404);
        }

        $statusVal = $customer->CusIsStoopeed ?? $customer->CusIsStopped ?? 0;

        return response()->json([
            'success' => true,
            'customer' => [
                'CustomersID'    => $customer->CustomersID,
                'CustomersName2' => $customer->CustomersName2,
                'CusPhone'       => $customer->CusPhone,
                'CusAddress'     => $customer->CusAddress,
                'CusIsStopped'   => $statusVal,
                'CusIsStoopeed'  => $statusVal,
                'accountID'      => $customer->accountID,
                'account'        => $customer->account,
            ],
        ]);
    }

    // =====================================================
    // إضافة عميل
    // =====================================================

    public function store(
        Request $request
    ) {
        $request->validate([
            'CustomersName2' => 'required|string|max:255',
            'accountID'      => 'required|exists:characcount,accountID',
        ], [
            'CustomersName2.required' => 'اسم العميل مطلوب',
            'accountID.required'      => 'يجب اختيار الحساب المحاسبي المرتبط',
            'accountID.exists'        => 'الحساب المحاسبي المختار غير صالح',
        ]);

        $status = $request->input('CusIsStopped', $request->input('CusIsStoopeed', 0));

        $customer = Customer::create([
            'CustomersName2' => $request->CustomersName2,
            'accountID'      => $request->accountID,
            'CusPhone'       => $request->CusPhone,
            'CusAddress'     => $request->CusAddress,
            'CusIsStoopeed'  => $status,
        ]);

        return response()->json([
            'success'  => true,
            'message'  => 'تم إضافة العميل بنجاح',
            'customer' => $customer->fresh(),
        ]);
    }

    // =====================================================
    // تحديث العميل
    // =====================================================

    public function update(
        Request $request,
        int|string $id
    ) {
        $request->validate([
            'CustomersName2' => 'required|string|max:255',
            'accountID'      => 'required|exists:characcount,accountID',
        ], [
            'CustomersName2.required' => 'اسم العميل مطلوب',
            'accountID.required'      => 'يجب اختيار الحساب المحاسبي المرتبط',
            'accountID.exists'        => 'الحساب المحاسبي المختار غير صالح',
        ]);

        $customer = Customer::find($id);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'العميل غير موجود',
            ], 404);
        }

        $status = $request->input('CusIsStopped', $request->input('CusIsStoopeed', 0));

        $customer->update([
            'CustomersName2' => $request->CustomersName2,
            'accountID'      => $request->accountID,
            'CusPhone'       => $request->CusPhone,
            'CusAddress'     => $request->CusAddress,
            'CusIsStoopeed'  => $status,
        ]);

        return response()->json([
            'success'  => true,
            'message'  => 'تم تحديث بيانات العميل بنجاح',
            'customer' => $customer->fresh(),
        ]);
    }

    // =====================================================
    // حذف العميل
    // =====================================================

    public function destroy(
        int|string $id
    ) {
        $customer = Customer::find($id);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'العميل غير موجود',
            ], 404);
        }

        try {
            $customerID = $customer->CustomersID;
            $customer->delete();

            return response()->json([
                'success'    => true,
                'message'    => 'تم حذف العميل بنجاح',
                'customerID' => $customerID,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف العميل لوجود حركات أو فواتير مرتبطة به',
            ], 422);
        }
    }

    // =====================================================
    // البحث في قائمة العملاء
    // =====================================================

    public function list(
        Request $request
    ) {
        $query = Customer::with('account')->orderBy('CustomersID', 'DESC');

        if ($request->filled('search_name')) {
            $query->where('CustomersName2', 'like', '%' . $request->search_name . '%');
        }

        if ($request->filled('search_phone')) {
            $query->where('CusPhone', 'like', '%' . $request->search_phone . '%');
        }

        if ($request->filled('search_code')) {
            $query->whereHas('account', function ($q) use ($request) {
                $q->where('accCode', 'like', '%' . $request->search_code . '%');
            });
        }

        $customers = $query->get();

        $html = '';

        foreach ($customers as $index => $customer) {

            $accountBadge = $customer->account
                ? '<span class="badge bg-light text-dark border">' . e($customer->account->accCode) . ' - ' . e($customer->account->accName) . '</span>'
                : '<span class="text-muted">--</span>';

            $statusVal = $customer->CusIsStoopeed ?? $customer->CusIsStopped ?? 0;

            $statusBadge = $statusVal == 0
                ? '<span class="badge bg-primary">نشط</span>'
                : '<span class="badge bg-secondary">متوقف</span>';

            $html .= '<tr class="customer-row" data-id="' . $customer->CustomersID . '">
                        <td>' . ($index + 1) . '</td>
                        <td class="fw-semibold">' . e($customer->CustomersName2) . '</td>
                        <td>' . e($customer->CusPhone ?? '--') . '</td>
                        <td>' . e($customer->CusAddress ?? '--') . '</td>
                        <td>' . $accountBadge . '</td>
                        <td>' . $statusBadge . '</td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-outline-primary me-1 edit-customer" data-id="' . $customer->CustomersID . '">
                                <i class="bi bi-pencil"></i> تعديل
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger delete-customer" data-id="' . $customer->CustomersID . '">
                                <i class="bi bi-trash"></i> حذف
                            </button>
                        </td>
                      </tr>';
        }

        if (empty($html)) {
            $html = '<tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="bi bi-people fs-2 d-block mb-2 text-secondary"></i>
                            لا يوجد عملاء مطابقين للبحث
                        </td>
                     </tr>';
        }

        return response()->json([
            'success' => true,
            'html'    => $html,
        ]);
    }
}