<?php

namespace App\Http\Controllers\accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\CharAccount;
use Illuminate\Http\Request;

class CharAccountController extends Controller
{
    // =========================================
    // عرض دليل الحسابات
    // =========================================

    public function index()
    {
        $accounts = CharAccount::orderBy('accCode')->get();

        return view(
            'setting.accounting.chartOfAccounts.index',
            compact('accounts')
        );
    }


    // =========================================
    // إضافة حساب
    // =========================================

    public function store(Request $request)
    {
        $request->validate([
            'accTypeID'  => 'required|numeric',
            'accCode'    => 'required|numeric|unique:characcount,accCode',
            'accParent'  => 'nullable|numeric',
            'accName'    => 'required|string',
            'nature'     => 'required|numeric',
            'accLevel'   => 'required|numeric',
            'IsActive'   => 'required|numeric',
            'isPostable' => 'required|numeric',
        ]);


        $account = CharAccount::create([

            'accTypeID'  => $request->accTypeID,
            'accCode'    => $request->accCode,
            'accParent'  => $request->accParent,
            'accName'    => $request->accName,
            'nature'     => $request->nature,
            'accLevel'   => $request->accLevel,
            'IsActive'   => $request->IsActive,
            'isPostable' => $request->isPostable,

        ]);


        return response()->json([

            'success' => true,

            'message' => 'تمت إضافة الحساب بنجاح',

            'account' => $account,

        ]);
    }


    // =========================================
    // جلب بيانات حساب واحد للتعديل
    // =========================================

    public function edit(CharAccount $account)
    {
        return response()->json([

            'success' => true,

            'account' => $account,

        ]);
    }


    // =========================================
    // تحديث الحساب
    // =========================================

    public function update(Request $request, CharAccount $account)
    {
        $request->validate([

            'accTypeID'  => 'required|numeric',

            'accCode'    => 'required|numeric|unique:characcount,accCode,' . $account->accountID . ',accountID',

            'accParent'  => 'nullable|numeric',

            'accName'    => 'required|string',

            'nature'     => 'required|numeric',

            'accLevel'   => 'required|numeric',

            'IsActive'   => 'required|numeric',

            'isPostable' => 'required|numeric',

        ]);


        $account->update([

            'accTypeID'  => $request->accTypeID,

            'accCode'    => $request->accCode,

            'accParent'  => $request->accParent,

            'accName'    => $request->accName,

            'nature'     => $request->nature,

            'accLevel'   => $request->accLevel,

            'IsActive'   => $request->IsActive,

            'isPostable' => $request->isPostable,

        ]);


        return response()->json([

            'success' => true,

            'message' => 'تم تعديل الحساب بنجاح',

            'account' => $account,

        ]);
    }

    //دالة البحث
    public function list(Request $request)
{
    $query = CharAccount::query();

    // البحث برقم الحساب
    if ($request->filled('search_code')) {
        $query->where(
            'accCode',
            'like',
            '%' . $request->search_code . '%'
        );
    }

    $accounts = $query
        ->orderBy('accCode')
        ->get();

    return view(
        'setting.accounting.chartOfAccounts.display',
        compact('accounts')
    );
}
}