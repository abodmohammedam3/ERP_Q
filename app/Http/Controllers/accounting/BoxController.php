<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Box;
use App\Models\Accounting\CharAccount;
use App\Models\Accounting\Coin;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class BoxController extends Controller
{
    // =====================================================
    // الحصول على حساب "الصناديق" الأب
    // =====================================================
    private function getParentAccount()
    {
        $parent = CharAccount::where('accName', 'الصناديق')->first();
        if ($parent) return $parent;

        $parent = CharAccount::where('accName', 'LIKE', '%صناديق%')->first();
        if ($parent) return $parent;

        throw new \Exception('لم يتم العثور على حساب "الصناديق" في دليل الحسابات. يرجى إنشاؤه أولاً.');
    }

    // =====================================================
    // توليد رقم الحساب الفرعي التالي
    // =====================================================
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

    // =====================================================
    // عرض الصفحة
    // =====================================================
    public function index()
    {
        try {
            $this->getParentAccount();
            $hasParent = true;
        } catch (\Exception $e) {
            $hasParent = false;
        }

        $boxes = Box::with(['account', 'coin'])
            ->orderBy('boxID', 'asc')
            ->get();

        $coins = Coin::where('is_active', 1)
            ->orderBy('coinsID', 'asc')
            ->get();

        return view('setting.accounting.boxes.index', compact('boxes', 'coins', 'hasParent'));
    }

    // =====================================================
    // جلب البيانات
    // =====================================================
    public function list()
    {
        $boxes = Box::with(['account', 'coin'])
            ->orderBy('boxID', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $boxes,
        ]);
    }

    // =====================================================
    // رقم الحساب التالي
    // =====================================================
    public function getNextCode()
    {
        try {
            $parent = $this->getParentAccount();
            $nextCode = $this->generateNextChildCode($parent);
            return response()->json(['success' => true, 'code' => $nextCode]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    // =====================================================
    // إضافة صندوق
    // =====================================================
    public function store(Request $request)
    {
        try {
            $this->getParentAccount();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'boxName'   => 'required|string|max:255|unique:boxes,boxName',
            'coinsID'   => 'nullable|exists:coins,coinsID',
            'is_active' => 'nullable|boolean',
        ], [
            'boxName.required' => 'اسم الصندوق مطلوب',
            'boxName.unique'   => 'اسم الصندوق موجود بالفعل',
            'coinsID.exists'   => 'العملة المختارة غير صحيحة',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            DB::transaction(function () use ($request) {
                $parent   = $this->getParentAccount();
                $nextCode = $this->generateNextChildCode($parent);

                // 1) إنشاء الحساب (سيتولى Observer إنشاء الصندوق)
                $account = CharAccount::create([
                    'accTypeID'  => $parent->accTypeID,
                    'accCode'    => $nextCode,
                    'accParent'  => $parent->accountID,
                    'accName'    => $request->boxName,
                    'nature'     => $parent->nature,
                    'accLevel'   => $parent->accLevel + 1,
                    'IsActive'   => $request->boolean('is_active', true) ? 1 : 0,
                    'isPostable' => 1,
                ]);

                // 2) تحديث العملة للصندوق المُنشأ تلقائياً
                $box = Box::where('accountID', $account->accountID)->first();
                if ($box) {
                    $box->update(['coinsID' => $request->coinsID ?: null]);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'تم إضافة الصندوق بنجاح',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    // =====================================================
    // تحديث صندوق
    // =====================================================
    public function update(Request $request, $id)
    {
        try {
            $box = Box::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'boxName' => [
                    'required', 'string', 'max:255',
                    Rule::unique('boxes', 'boxName')->ignore($box->boxID, 'boxID'),
                ],
                'coinsID'   => 'nullable|exists:coins,coinsID',
                'is_active' => 'nullable|boolean',
            ], [
                'boxName.required' => 'اسم الصندوق مطلوب',
                'boxName.unique'   => 'اسم الصندوق موجود بالفعل',
                'coinsID.exists'   => 'العملة المختارة غير صحيحة',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                    'errors'  => $validator->errors(),
                ], 422);
            }

            DB::transaction(function () use ($request, $box) {
                $box->update([
                    'boxName'   => $request->boxName,
                    'coinsID'   => $request->coinsID ?: null,
                    'is_active' => $request->boolean('is_active', true) ? 1 : 0,
                ]);

                // مزامنة الحساب
                $account = CharAccount::find($box->accountID);
                if ($account) {
                    $account->accName  = $request->boxName;
                    $account->IsActive = $request->boolean('is_active', true) ? 1 : 0;
                    $account->save();
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث الصندوق بنجاح',
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'الصندوق غير موجود'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء التحديث: ' . $e->getMessage(),
            ], 422);
        }
    }

    // =====================================================
    // حذف صندوق (نفس منطق StockController)
    // =====================================================
    public function destroy($id)
    {
        try {
            $box = Box::findOrFail($id);
            $accountId = $box->accountID;

            DB::transaction(function () use ($box, $accountId) {
                // حذف الصندوق
                $box->delete();

                // حذف الحساب إن لم يكن له أبناء
                $account = CharAccount::find($accountId);
                if ($account) {
                    $hasChildren = CharAccount::where('accParent', $accountId)->exists();
                    if (!$hasChildren) {
                        $account->delete();
                    } else {
                        $account->update(['IsActive' => 0]);
                    }
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'تم حذف الصندوق بنجاح',
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'الصندوق غير موجود'], 404);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() == 23000) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن حذف الصندوق لأن الحساب المرتبط به مستخدم في مكان آخر.',
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

    // =====================================================
    // تبديل الحالة
    // =====================================================
    public function toggleStatus($id)
    {
        try {
            $box = Box::findOrFail($id);
            $newStatus = !$box->is_active;

            DB::transaction(function () use ($box, $newStatus) {
                $box->update(['is_active' => $newStatus]);

                $account = CharAccount::find($box->accountID);
                if ($account) {
                    $account->update(['IsActive' => $newStatus ? 1 : 0]);
                }
            });

            return response()->json([
                'success' => true,
                'message' => $newStatus ? 'تم التفعيل بنجاح' : 'تم التعطيل بنجاح',
                'data'    => $box->fresh('account'),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: ' . $e->getMessage(),
            ], 422);
        }
    }
}