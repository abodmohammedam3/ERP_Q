<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Stock;
use App\Models\Accounting\CharAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    /**
     * الحصول على حساب "المخازن" الأب (بدقة عالية)
     */
    private function getParentAccount()
    {
        // 1. البحث عن حساب اسمه "المخازن" بالضبط
        $parent = CharAccount::where('accName', 'المخازن')->first();
        if ($parent) return $parent;

        // 2. البحث عن حساب يحتوي اسمه على "مخازن" (كحل بديل)
        $parent = CharAccount::where('accName', 'LIKE', '%مخازن%')->first();
        if ($parent) return $parent;

        

        // 4. لم نجد شيئاً
        throw new \Exception('لم يتم العثور على حساب "المخازن" في دليل الحسابات. يرجى إنشاؤه أولاً.');
    }

    /**
     * توليد رقم الحساب الفرعي التالي (مع دعم الأعماق المختلفة)
     */
    private function generateNextChildCode($parent)
    {
        $childLevel = $parent->accLevel + 1;
        $segmentLength = ($childLevel === 2) ? 1 : 2;

        $children = CharAccount::where('accParent', $parent->accountID)
            ->orderBy('accCode')
            ->get(['accCode']);

        $maxSequence = 0;
        $parentCode = (string) $parent->accCode;

        foreach ($children as $child) {
            $childCode = (string) $child->accCode;
            if (!str_starts_with($childCode, $parentCode)) continue;
            $suffix = substr($childCode, strlen($parentCode));
            if (strlen($suffix) !== $segmentLength || !ctype_digit($suffix)) continue;
            $seq = (int) $suffix;
            if ($seq > $maxSequence) $maxSequence = $seq;
        }

        $nextSequence = $maxSequence + 1;
        $maxAllowed = ($segmentLength === 1) ? 9 : 99;
        if ($nextSequence > $maxAllowed) {
            throw new \Exception('تم الوصول إلى الحد الأقصى للحسابات الفرعية في هذا المستوى');
        }

        $segment = str_pad((string) $nextSequence, $segmentLength, '0', STR_PAD_LEFT);
        return $parentCode . $segment;
    }

    // =========================================================
    // عرض الصفحة الرئيسية (مع التحقق من وجود الأب)
    // =========================================================
    public function index()
    {
        try {
            $this->getParentAccount();
            $hasParent = true;
        } catch (\Exception $e) {
            $hasParent = false;
        }

        $stocks = Stock::with('account')->orderBy('StockID', 'asc')->get();
        return view('setting.inventory.warehouses.index', compact('stocks', 'hasParent'));
    }

    // =========================================================
    // جلب البيانات عبر AJAX
    // =========================================================
    public function list(Request $request)
    {
        $stocks = Stock::with('account')->orderBy('StockID', 'asc')->get();
        $stocks->each(function ($stock) {
            $stock->accountDisplay = $stock->account ? $stock->account->accCode : '---';
        });
        return response()->json([
            'success' => true,
            'data' => $stocks,
        ]);
    }

    // =========================================================
    // جلب رقم الحساب التالي (للعرض في المودال)
    // =========================================================
    public function getNextCode()
    {
        try {
            $parent = $this->getParentAccount();
            $nextCode = $this->generateNextChildCode($parent);
            return response()->json([
                'success' => true,
                'code' => $nextCode,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    // =========================================================
    // إضافة مخزن جديد (ينشئ الحساب فقط، والمخزن يتم عبر Observer)
    // =========================================================
    public function store(Request $request)
    {
        // التحقق من وجود الأب أولاً
        try {
            $this->getParentAccount();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'StockName' => 'required|string|max:255|unique:stocks,StockName',
        ], [
            'StockName.unique' => 'هذا المخزن موجود بالفعل',
            'StockName.required' => 'اسم المخزن مطلوب',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first('StockName'),
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::transaction(function () use ($request) {
                $parent = $this->getParentAccount();
                $nextCode = $this->generateNextChildCode($parent);

                // إنشاء الحساب فقط (سيقوم Observer بإنشاء المخزن تلقائياً)
                CharAccount::create([
                    'accTypeID' => $parent->accTypeID,
                    'accCode' => $nextCode,
                    'accParent' => $parent->accountID,
                    'accName' => $request->StockName,
                    'nature' => $parent->nature,
                    'accLevel' => $parent->accLevel + 1,
                    'IsActive' => 1,
                    'isPostable' => 1,
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'تم إضافة المخزن والحساب المحاسبي بنجاح',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    // =========================================================
    // تحديث المخزن (اسم فقط)
    // =========================================================
    public function update(Request $request, $id)
    {
        try {
            $stock = Stock::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'StockName' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('stocks', 'StockName')->ignore($stock->StockID, 'StockID'),
                ],
            ], [
                'StockName.unique' => 'هذا المخزن موجود بالفعل',
                'StockName.required' => 'اسم المخزن مطلوب',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first('StockName'),
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::transaction(function () use ($request, $stock) {
                $stock->update(['StockName' => $request->StockName]);

                $account = CharAccount::find($stock->accountID);
                if ($account) {
                    $account->accName = $request->StockName;
                    $account->save();
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث المخزن بنجاح',
                'data' => $stock->fresh('account'),
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'المخزن غير موجود',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء التحديث: ' . $e->getMessage(),
            ], 422);
        }
    }

    // =========================================================
    // حذف المخزن (مع حذف الحساب إن لم يكن له أبناء)
    // =========================================================
    public function destroy($id)
    {
        try {
            $stock = Stock::findOrFail($id);
            $accountId = $stock->accountID;

            DB::transaction(function () use ($stock, $accountId) {
                // حذف المخزن
                $stock->delete();

                // حذف الحساب إن لم يكن له أبناء
                $account = CharAccount::find($accountId);
                if ($account) {
                    $hasChildren = CharAccount::where('accParent', $accountId)->exists();
                    if (!$hasChildren) {
                        $account->delete();
                    } else {
                        // إذا كان له أبناء، نعطله (بدلاً من الحذف)
                        $account->update(['IsActive' => 0]);
                    }
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'تم حذف المخزن بنجاح',
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'المخزن غير موجود',
            ], 404);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() == 23000) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن حذف المخزن لأن الحساب المرتبط به مستخدم في مكان آخر.',
                ], 422);
            }
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء الحذف: ' . $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء الحذف: ' . $e->getMessage(),
            ], 422);
        }
    }

    // =========================================================
    // تبديل حالة التفعيل
    // =========================================================
    public function toggleStatus($id)
    {
        try {
            $stock = Stock::findOrFail($id);
            $newStatus = !$stock->is_active;

            DB::transaction(function () use ($stock, $newStatus) {
                $stock->update(['is_active' => $newStatus]);

                $account = CharAccount::find($stock->accountID);
                if ($account) {
                    $account->update(['IsActive' => $newStatus ? 1 : 0]);
                }
            });

            $statusText = $newStatus ? 'تم التفعيل' : 'تم التعطيل';
            return response()->json([
                'success' => true,
                'message' => $statusText . ' بنجاح',
                'data' => $stock->fresh('account'),
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'المخزن غير موجود',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تغيير الحالة: ' . $e->getMessage(),
            ], 422);
        }
    }
}