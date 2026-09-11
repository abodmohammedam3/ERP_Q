<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Stock;
use App\Models\Accounting\CharAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    /**
     * الحصول على الحساب الأب للمخازن.
     *
     * الحساب النظامي:
     * 1104 - المخزون
     */
    private function getParentAccount()
    {
        return CharAccount::where('system_key', 'inventory')
            ->where('isPostable', 0)
            ->first();
    }

    /**
     * توليد رقم الحساب التحليلي التالي.
     *
     * مثال:
     * 110401
     * 110402
     * 110403
     */
    private function generateNextChildCode(CharAccount $parent)
    {
        $prefix = $parent->accCode;

        $children = CharAccount::where('accParent', $parent->accountID)
            ->where('isPostable', 1)
            ->pluck('accCode');

        $maxNumber = 0;

        foreach ($children as $code) {

            if (!str_starts_with((string) $code, $prefix)) {
                continue;
            }

            $suffix = substr((string) $code, strlen($prefix));

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
     * صفحة المخازن.
     */
    public function index()
    {
        $parent = $this->getParentAccount();

        $stocks = Stock::with('account')
            ->orderBy('StockID', 'DESC')
            ->get();

        return view('setting.inventory.warehouses.index', [
            'stocks' => $stocks,
            'hasParent' => (bool) $parent,
        ]);
    }

    /**
     * قائمة المخازن AJAX.
     */
    public function list()
    {
        $stocks = Stock::with('account')
            ->orderBy('StockID', 'DESC')
            ->get()
            ->map(function ($stock) {

                return [
                    'StockID' => $stock->StockID,
                    'StockName' => $stock->StockName,
                    'accountDisplay' => $stock->account
                        ? $stock->account->accCode
                        : null,
                    'is_active' => (int) $stock->is_active,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $stocks,
        ]);
    }

    /**
     * الحصول على رقم الحساب التحليلي التالي.
     *
     * يستخدمه JavaScript لعرض الرقم داخل المودال
     * قبل عملية الحفظ.
     */
    public function getNextCode()
    {
        $parent = $this->getParentAccount();

        if (!$parent) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على الحساب الأب للمخازن.',
            ], 404);
        }

        $nextCode = $this->generateNextChildCode($parent);

        return response()->json([
            'success' => true,
            'code' => $nextCode,
        ]);
    }

    /**
     * إضافة مخزن جديد.
     */
    public function store(Request $request)
    {
        $request->validate([
            'StockName' => [
                'required',
                'string',
                'max:255',
            ],
        ], [
            'StockName.required' => 'اسم المخزن مطلوب.',
            'StockName.string' => 'اسم المخزن غير صحيح.',
            'StockName.max' => 'اسم المخزن يجب ألا يتجاوز 255 حرفاً.',
        ]);

        try {

            $result = DB::transaction(function () use ($request) {

                /*
                 * 1. الحصول على الحساب الأب
                 */
                $parent = $this->getParentAccount();

                if (!$parent) {
                    throw new \Exception(
                        'لم يتم العثور على الحساب الأب للمخازن.'
                    );
                }

                /*
                 * 2. توليد رقم الحساب التحليلي
                 */
                $nextCode = $this->generateNextChildCode($parent);

                /*
                 * 3. إنشاء الحساب التحليلي أولاً
                 *
                 * مهم جداً:
                 * stocks.accountID لا يقبل NULL
                 */
                $account = CharAccount::create([
                    'accParent' => $parent->accountID,
                    'accTypeID' => $parent->accTypeID,
                    'accCode' => $nextCode,
                    'accName' => $request->StockName,
                    'nature' => $parent->nature,
                    'accLevel' => $parent->accLevel + 1,
                    'IsActive' => 1,
                    'isPostable' => 1,
                    'is_system' => 0,
                    'system_key' => null,
                ]);

                /*
                 * 4. إنشاء المخزن وربطه بالحساب
                 */
                $stock = Stock::create([
                    'StockName' => $request->StockName,
                    'accountID' => $account->accountID,
                    'is_active' => 1,
                ]);

                return [
                    'stock' => $stock,
                    'account' => $account,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'تم إضافة المخزن وربطه بالحساب التحليلي بنجاح.',
                'data' => [
                    'StockID' => $result['stock']->StockID,
                    'StockName' => $result['stock']->StockName,
                    'accountID' => $result['account']->accountID,
                    'accountCode' => $result['account']->accCode,
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
     * تعديل المخزن.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'StockName' => [
                'required',
                'string',
                'max:255',
            ],
        ], [
            'StockName.required' => 'اسم المخزن مطلوب.',
            'StockName.string' => 'اسم المخزن غير صحيح.',
            'StockName.max' => 'اسم المخزن يجب ألا يتجاوز 255 حرفاً.',
        ]);

        try {

            $stock = Stock::findOrFail($id);

            /*
             * Observer سيتولى مزامنة اسم الحساب
             * بعد تحديث المخزن.
             */
            $stock->StockName = $request->StockName;
            $stock->save();

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث بيانات المخزن بنجاح.',
            ], 200);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * حذف المخزن.
     */
    public function destroy($id)
    {
        try {

            DB::transaction(function () use ($id) {

                $stock = Stock::findOrFail($id);

                $accountID = $stock->accountID;

                /*
                 * حذف المخزن أولاً.
                 */
                $stock->delete();

                /*
                 * التعامل مع الحساب التحليلي المرتبط.
                 */
                if ($accountID) {

                    $account = CharAccount::find($accountID);

                    if ($account) {

                        /*
                         * إذا كان الحساب لا يحتوي على أبناء
                         * يتم حذفه.
                         */
                        $hasChildren = CharAccount::where(
                            'accParent',
                            $account->accountID
                        )->exists();

                        if (!$hasChildren) {

                            $account->delete();

                        } else {

                            /*
                             * إذا كان له أبناء، لا نحذفه.
                             * فقط نعطله.
                             */
                            $account->IsActive = 0;
                            $account->save();
                        }
                    }
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'تم حذف المخزن بنجاح.',
            ], 200);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * تفعيل / تعطيل المخزن.
     */
    public function toggleStatus($id)
    {
        try {

            $stock = Stock::findOrFail($id);

            $stock->is_active = !$stock->is_active;
            $stock->save();

            /*
             * StockObserver سيقوم بمزامنة
             * حالة الحساب التحليلي.
             */

            return response()->json([
                'success' => true,
                'message' => $stock->is_active
                    ? 'تم تفعيل المخزن بنجاح.'
                    : 'تم تعطيل المخزن بنجاح.',
            ], 200);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}