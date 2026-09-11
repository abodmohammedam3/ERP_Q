<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Coin;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


class CoinController extends Controller
{
    public function index()
    {
        $coins = Coin::orderBy('coinsID', 'asc')->get();
        return view('setting.accounting.coins.index', compact('coins'));
    }

    public function list(Request $request)
    {
        $coins = Coin::orderBy('coinsID', 'asc')->get();
        return response()->json([
            'success' => true,
            'data'    => $coins,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'coinsName'         => 'required|string|max:100|unique:coins,coinsName',
            'coinsCode'         => 'required|string|max:10|unique:coins,coinsCode',
            'coinsExchangeRate' => 'required|numeric|min:0',
            'coinsSystem'       => 'nullable|boolean',
            'is_active'         => 'nullable|boolean',
        ], [
            'coinsName.required'         => 'اسم العملة مطلوب',
            'coinsName.unique'           => 'اسم العملة موجود بالفعل',
            'coinsCode.required'         => 'رمز العملة مطلوب',
            'coinsCode.unique'           => 'رمز العملة موجود بالفعل',
            'coinsExchangeRate.required' => 'سعر الصرف مطلوب',
            'coinsExchangeRate.numeric'  => 'سعر الصرف يجب أن يكون رقماً',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $isSystem = $request->boolean('coinsSystem');

        DB::transaction(function () use ($request, $isSystem) {
            // إذا كانت هذه عملة النظام، إلغاء العلم عن البقية
            if ($isSystem) {
                Coin::where('coinsSystem', 1)->update(['coinsSystem' => 0]);
            }

            Coin::create([
                'coinsName'         => $request->coinsName,
                'coinsCode'         => strtoupper($request->coinsCode),
                'coinsExchangeRate' => $request->coinsExchangeRate,
                'coinsSystem'       => $isSystem ? 1 : 0,
                'is_active'         => $request->boolean('is_active', true) ? 1 : 0,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة العملة بنجاح',
        ]);
    }

    public function update(Request $request, $id)
    {
        $coin = Coin::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'coinsName' => [
                'required', 'string', 'max:100',
                Rule::unique('coins', 'coinsName')->ignore($coin->coinsID, 'coinsID'),
            ],
            'coinsCode' => [
                'required', 'string', 'max:10',
                Rule::unique('coins', 'coinsCode')->ignore($coin->coinsID, 'coinsID'),
            ],
            'coinsExchangeRate' => 'required|numeric|min:0',
            'coinsSystem'       => 'nullable|boolean',
            'is_active'         => 'nullable|boolean',
        ], [
            'coinsName.required' => 'اسم العملة مطلوب',
            'coinsName.unique'   => 'اسم العملة موجود بالفعل',
            'coinsCode.required' => 'رمز العملة مطلوب',
            'coinsCode.unique'   => 'رمز العملة موجود بالفعل',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $isSystem = $request->boolean('coinsSystem');

        DB::transaction(function () use ($request, $coin, $isSystem) {
            if ($isSystem) {
                Coin::where('coinsID', '!=', $coin->coinsID)
                    ->where('coinsSystem', 1)
                    ->update(['coinsSystem' => 0]);
            }

            $coin->update([
                'coinsName'         => $request->coinsName,
                'coinsCode'         => strtoupper($request->coinsCode),
                'coinsExchangeRate' => $request->coinsExchangeRate,
                'coinsSystem'       => $isSystem ? 1 : 0,
                'is_active'         => $request->boolean('is_active', true) ? 1 : 0,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث العملة بنجاح',
        ]);
    }

    public function destroy($id)
{
    $coin = Coin::findOrFail($id);

    // منع حذف عملة النظام
    if ($coin->coinsSystem) {
        return response()->json([
            'success' => false,
            'message' => 'لا يمكن حذف العملة الأساسية للنظام',
        ], 422);
    }

    // التحقق من الارتباط بجدول البنوك (إن كان موجوداً)
    if (Schema::hasTable('banks') && DB::table('banks')->where('coinsID', $coin->coinsID)->exists()) {
        return response()->json([
            'success' => false,
            'message' => 'لا يمكن حذف العملة لارتباطها ببنوك',
        ], 422);
    }

    // التحقق من الارتباط بجدول الصناديق (إن كان موجوداً)
    if (Schema::hasTable('boxes') && DB::table('boxes')->where('coinsID', $coin->coinsID)->exists()) {
        return response()->json([
            'success' => false,
            'message' => 'لا يمكن حذف العملة لارتباطها بصناديق',
        ], 422);
    }

    $coin->delete();

    return response()->json([
        'success' => true,
        'message' => 'تم حذف العملة بنجاح',
    ]);
}

    public function toggleStatus($id)
    {
        $coin = Coin::findOrFail($id);

        // منع تعطيل عملة النظام
        if ($coin->coinsSystem && $coin->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن تعطيل العملة الأساسية للنظام',
            ], 422);
        }

        $newStatus = !$coin->is_active;
        $coin->update(['is_active' => $newStatus]);

        return response()->json([
            'success' => true,
            'message' => $newStatus ? 'تم التفعيل بنجاح' : 'تم التعطيل بنجاح',
            'data'    => $coin,
        ]);
    }
}