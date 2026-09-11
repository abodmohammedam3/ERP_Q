<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Bank;
use App\Models\Accounting\CharAccount;
use App\Models\Accounting\Coin;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class BankController extends Controller
{
    private function getParentAccount()
    {
        $parent = CharAccount::where('accName', 'البنوك')->first()
            ?? CharAccount::where('accName', 'LIKE', '%بنوك%')->first();

        if (!$parent) {
            throw new \Exception('لم يتم العثور على حساب "البنوك" في دليل الحسابات. يرجى إنشاؤه أولاً.');
        }

        return $parent;
    }

    private function generateNextChildCode($parent)
    {
        $childLevel    = $parent->accLevel + 1;
        $segmentLength = ($childLevel === 2) ? 1 : 2;

        $children = CharAccount::where('accParent', $parent->accountID)
            ->orderBy('accCode')->get(['accCode']);

        $maxSequence = 0;
        $parentCode  = (string) $parent->accCode;

        foreach ($children as $child) {
            $childCode = (string) $child->accCode;
            if (!str_starts_with($childCode, $parentCode)) continue;
            $suffix = substr($childCode, strlen($parentCode));
            if (strlen($suffix) !== $segmentLength || !ctype_digit($suffix)) continue;
            $seq = (int) $suffix;
            if ($seq > $maxSequence) $maxSequence = $seq;
        }

        $nextSequence = $maxSequence + 1;
        $maxAllowed   = ($segmentLength === 1) ? 9 : 99;
        if ($nextSequence > $maxAllowed) {
            throw new \Exception('تم الوصول إلى الحد الأقصى للحسابات الفرعية في هذا المستوى');
        }

        $segment = str_pad((string) $nextSequence, $segmentLength, '0', STR_PAD_LEFT);
        return $parentCode . $segment;
    }

    public function index()
    {
        try {
            $this->getParentAccount();
            $hasParent = true;
        } catch (\Exception $e) {
            $hasParent = false;
        }

        $banks = Bank::with(['account', 'coin'])->orderBy('bankID', 'asc')->get();
        $coins = Coin::where('is_active', 1)->orderBy('coinsID', 'asc')->get();

        return view('setting.accounting.banks.index', compact('banks', 'coins', 'hasParent'));
    }

    public function list()
    {
        $banks = Bank::with(['account', 'coin'])->orderBy('bankID', 'asc')->get();

        return response()->json(['success' => true, 'data' => $banks]);
    }

    public function getNextCode()
    {
        try {
            $parent   = $this->getParentAccount();
            $nextCode = $this->generateNextChildCode($parent);
            return response()->json(['success' => true, 'code' => $nextCode]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function store(Request $request)
    {
        try {
            $this->getParentAccount();
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $validator = Validator::make($request->all(), [
            'bankName'      => 'required|string|max:255|unique:banks,bankName',
            'coinsID'       => 'nullable|exists:coins,coinsID',
            'accountNumber' => 'nullable|string|max:50',
            'is_active'     => 'nullable|boolean',
        ], [
            'bankName.required' => 'اسم البنك مطلوب',
            'bankName.unique'   => 'اسم البنك موجود بالفعل',
            'coinsID.exists'    => 'العملة المختارة غير صحيحة',
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

                // إنشاء الحساب (Observer ينشئ البنك تلقائياً)
                $account = CharAccount::create([
                    'accTypeID'  => $parent->accTypeID,
                    'accCode'    => $nextCode,
                    'accParent'  => $parent->accountID,
                    'accName'    => $request->bankName,
                    'nature'     => $parent->nature,
                    'accLevel'   => $parent->accLevel + 1,
                    'IsActive'   => $request->boolean('is_active', true) ? 1 : 0,
                    'isPostable' => 1,
                ]);

                // تحديث حقول البنك
                $bank = Bank::where('accountID', $account->accountID)->first();
                if ($bank) {
                    $bank->update([
                        'coinsID'       => $request->coinsID ?: null,
                        'accountNumber' => $request->accountNumber ?: null,
                    ]);
                }
            });

            return response()->json(['success' => true, 'message' => 'تم إضافة البنك بنجاح']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $bank = Bank::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'bankName' => [
                    'required', 'string', 'max:255',
                    Rule::unique('banks', 'bankName')->ignore($bank->bankID, 'bankID'),
                ],
                'coinsID'       => 'nullable|exists:coins,coinsID',
                'accountNumber' => 'nullable|string|max:50',
                'is_active'     => 'nullable|boolean',
            ], [
                'bankName.required' => 'اسم البنك مطلوب',
                'bankName.unique'   => 'اسم البنك موجود بالفعل',
                'coinsID.exists'    => 'العملة المختارة غير صحيحة',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                    'errors'  => $validator->errors(),
                ], 422);
            }

            DB::transaction(function () use ($request, $bank) {
                $bank->update([
                    'bankName'      => $request->bankName,
                    'coinsID'       => $request->coinsID ?: null,
                    'accountNumber' => $request->accountNumber ?: null,
                    'is_active'     => $request->boolean('is_active', true) ? 1 : 0,
                ]);

                $account = CharAccount::find($bank->accountID);
                if ($account) {
                    $account->accName  = $request->bankName;
                    $account->IsActive = $request->boolean('is_active', true) ? 1 : 0;
                    $account->save();
                }
            });

            return response()->json(['success' => true, 'message' => 'تم تحديث البنك بنجاح']);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'البنك غير موجود'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء التحديث: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function destroy($id)
    {
        try {
            $bank = Bank::findOrFail($id);
            $accountId = $bank->accountID;

            DB::transaction(function () use ($bank, $accountId) {
                $bank->delete();

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

            return response()->json(['success' => true, 'message' => 'تم حذف البنك بنجاح']);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'البنك غير موجود'], 404);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() == 23000) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن حذف البنك لأن الحساب المرتبط به مستخدم في مكان آخر.',
                ], 422);
            }
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function toggleStatus($id)
    {
        try {
            $bank = Bank::findOrFail($id);
            $newStatus = !$bank->is_active;

            DB::transaction(function () use ($bank, $newStatus) {
                $bank->update(['is_active' => $newStatus]);

                $account = CharAccount::find($bank->accountID);
                if ($account) {
                    $account->update(['IsActive' => $newStatus ? 1 : 0]);
                }
            });

            return response()->json([
                'success' => true,
                'message' => $newStatus ? 'تم التفعيل بنجاح' : 'تم التعطيل بنجاح',
                'data'    => $bank->fresh('account'),
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}