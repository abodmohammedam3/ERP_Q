<?php

namespace App\Http\Controllers\accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\CharAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CharAccountController extends Controller
{
    // =====================================================
    // عرض دليل الحسابات
    // =====================================================

    public function index()
    {
        $accounts =
            CharAccount::orderBy('accCode')->get();

        return view(
            'setting.accounting.chartOfAccounts.index',
            compact('accounts')
        );
    }


    // =====================================================
    // جلب حساب للتعديل
    // =====================================================

    public function edit(
        CharAccount $account
    ) {

        return response()->json([

            'success' => true,

            'account' => [
                'accountID' =>
                    $account->accountID,

                'accTypeID' =>
                    $account->accTypeID,

                'accCode' =>
                    $account->accCode,

                'accParent' =>
                    $account->accParent,

                'accName' =>
                    $account->accName,

                'nature' =>
                    $account->nature,

                'accLevel' =>
                    $account->accLevel,

                'IsActive' =>
                    $account->IsActive,

                'isPostable' =>
                    $account->isPostable,
            ],

        ]);
    }


    // =====================================================
    // إضافة حساب
    // =====================================================

    public function store(
        Request $request
    ) {

        // =================================================
        // التحقق من البيانات الأساسية
        // =================================================

        $request->validate(
            [
                'accTypeID' =>
                    'required|numeric',

                'accParent' =>
                    'nullable|numeric',

                'accCode' =>
                    'nullable|numeric',

                'accName' =>
                    'required|string',

                'nature' =>
                    'required|numeric',

                'IsActive' =>
                    'required|numeric',

                'isPostable' =>
                    'required|numeric',
            ],
            [
                'accName.required' =>
                    'اسم الحساب مطلوب',
            ]
        );


        // =================================================
        // تحديد الحساب الأب
        // =================================================

        $parent = null;

        if ($request->filled('accParent')) {

            $parent =
                CharAccount::find(
                    $request->accParent
                );

            if (!$parent) {

                return response()->json([
                    'success' => false,
                    'message' =>
                        'الحساب الأب غير موجود',
                ], 422);
            }
        }


        // =================================================
        // تحديد المستوى
        // =================================================

        if ($parent) {

            $accLevel =
                ((int) $parent->accLevel) + 1;

        } else {

            $accLevel = 1;
        }


        // =================================================
        // تحديد رقم الحساب
        // =================================================

        if (!$parent) {

            // =============================================
            // حساب رئيسي
            // =============================================

            if (!$request->filled('accCode')) {

                return response()->json([
                    'success' => false,
                    'message' =>
                        'رقم الحساب مطلوب للحساب الرئيسي',
                ], 422);
            }

            $accCode =
                trim($request->accCode);


            if (!ctype_digit($accCode)) {

                return response()->json([
                    'success' => false,
                    'message' =>
                        'رقم الحساب الرئيسي يجب أن يكون رقماً صحيحاً',
                ], 422);
            }


            if (strlen($accCode) !== 1) {

                return response()->json([
                    'success' => false,
                    'message' =>
                        'رقم الحساب الرئيسي يجب أن يتكون من خانة واحدة مثل 1 أو 2 أو 3',
                ], 422);
            }

        } else {

            // =============================================
            // حساب فرعي
            // =============================================

            try {

                $accCode =
                    $this->generateChildCode(
                        $parent
                    );

            } catch (\Throwable $e) {

                return response()->json([
                    'success' => false,
                    'message' =>
                        $e->getMessage(),
                ], 422);
            }
        }


        // =================================================
        // التأكد من عدم تكرار الرقم
        // =================================================

        if (
            CharAccount::where(
                'accCode',
                $accCode
            )->exists()
        ) {

            return response()->json([
                'success' => false,
                'message' =>
                    'رقم الحساب موجود مسبقاً',
            ], 422);
        }


        // =================================================
        // إنشاء الحساب
        // =================================================

        $account =
            CharAccount::create([

                'accTypeID' =>
                    $request->accTypeID,

                'accCode' =>
                    $accCode,

                'accParent' =>
                    $parent?->accountID,

                'accName' =>
                    $request->accName,

                'nature' =>
                    $request->nature,

                'accLevel' =>
                    $accLevel,

                'IsActive' =>
                    $request->IsActive,

                'isPostable' =>
                    $request->isPostable,

            ]);


        // =================================================
        // إرجاع الحساب
        // =================================================

        return response()->json([

            'success' => true,

            'message' =>
                'تمت إضافة الحساب بنجاح',

            'account' =>
                $account->fresh(),

        ]);
    }


    // =====================================================
    // توليد رقم الحساب الفرعي التالي
    // =====================================================

    private function generateChildCode(
        CharAccount $parent
    ): string {

        $parentCode =
            (string) $parent->accCode;

        $parentLevel =
            (int) $parent->accLevel;

        $childLevel =
            $parentLevel + 1;


        // =================================================
        // المستوى الثاني يضيف خانة واحدة
        // المستوى الثالث وما بعده يضيف خانتين
        // =================================================

        if ($childLevel === 2) {

            $segmentLength = 1;

        } else {

            $segmentLength = 2;
        }


        // =================================================
        // جلب أبناء الحساب الأب
        // =================================================

        $children =
            CharAccount::where(
                'accParent',
                $parent->accountID
            )
            ->orderBy('accCode')
            ->get([
                'accCode'
            ]);


        // =================================================
        // البحث عن أكبر تسلسل
        // =================================================

        $maxSequence = 0;

        foreach ($children as $child) {

            $childCode =
                (string) $child->accCode;


            if (
                !str_starts_with(
                    $childCode,
                    $parentCode
                )
            ) {
                continue;
            }


            $suffix =
                substr(
                    $childCode,
                    strlen($parentCode)
                );


            if (
                strlen($suffix) !==
                $segmentLength
            ) {
                continue;
            }


            if (
                !ctype_digit($suffix)
            ) {
                continue;
            }


            $sequence =
                (int) $suffix;


            if (
                $sequence >
                $maxSequence
            ) {

                $maxSequence =
                    $sequence;
            }
        }


        // =================================================
        // الرقم التالي
        // =================================================

        $nextSequence =
            $maxSequence + 1;


        // =================================================
        // الحد الأقصى
        // =================================================

        $maxAllowed =
            $segmentLength === 1
                ? 9
                : 99;


        if (
            $nextSequence >
            $maxAllowed
        ) {

            throw new \RuntimeException(
                'تم الوصول إلى الحد الأقصى للحسابات الفرعية في هذا المستوى'
            );
        }


        // =================================================
        // تكوين الجزء الجديد
        // =================================================

        $segment =
            str_pad(
                (string) $nextSequence,
                $segmentLength,
                '0',
                STR_PAD_LEFT
            );


        return $parentCode . $segment;
    }


    // =====================================================
    // تحديث الحساب
    // =====================================================

    public function update(
        Request $request,
        CharAccount $account
    ) {

        // =================================================
        // التحقق من البيانات
        // =================================================

        $request->validate(
            [
                'accTypeID' =>
                    'required|numeric',

                'accCode' =>
                    'required|numeric|unique:characcount,accCode,'
                    . $account->accountID
                    . ',accountID',

                'accParent' =>
                    'nullable|numeric',

                'accName' =>
                    'required|string',

                'nature' =>
                    'required|numeric',

                'IsActive' =>
                    'required|numeric',

                'isPostable' =>
                    'required|numeric',
            ],
            [
                'accCode.unique' =>
                    'رقم الحساب موجود مسبقاً',

                'accName.required' =>
                    'اسم الحساب مطلوب',
            ]
        );


        // =================================================
        // الحساب الأب الجديد
        // =================================================

        $newParent = null;

        if ($request->filled('accParent')) {

            $newParentId =
                (int) $request->accParent;


            // منع الحساب من أن يكون أباً لنفسه

            if (
                $newParentId ===
                (int) $account->accountID
            ) {

                return response()->json([
                    'success' => false,
                    'message' =>
                        'لا يمكن أن يكون الحساب أباً لنفسه',
                ], 422);
            }


            $newParent =
                CharAccount::find(
                    $newParentId
                );


            if (!$newParent) {

                return response()->json([
                    'success' => false,
                    'message' =>
                        'الحساب الأب غير موجود',
                ], 422);
            }


            // منع نقل الحساب إلى أحد أبنائه

            if (
                $this->isDescendant(
                    $newParent->accountID,
                    $account->accountID
                )
            ) {

                return response()->json([
                    'success' => false,
                    'message' =>
                        'لا يمكن نقل الحساب إلى أحد حساباته الفرعية',
                ], 422);
            }
        }


        // =================================================
        // الأب القديم والجديد
        // =================================================

        $oldParentId =
            $account->accParent;

        $newParentId =
            $newParent?->accountID;


        $parentChanged =
            (int) ($oldParentId ?? 0) !==
            (int) ($newParentId ?? 0);


        // =================================================
        // المستوى الجديد
        // =================================================

        if ($newParent) {

            $newLevel =
                ((int) $newParent->accLevel) + 1;

        } else {

            $newLevel = 1;
        }


        // =================================================
        // الرقم الجديد
        // =================================================

        $newCode =
            trim($request->accCode);


        // =================================================
        // إذا تغير الأب يتم إنشاء كود جديد
        // =================================================

        if (
            $parentChanged &&
            $newParent
        ) {

            try {

                $newCode =
                    $this->generateChildCode(
                        $newParent
                    );

            } catch (\Throwable $e) {

                return response()->json([
                    'success' => false,
                    'message' =>
                        $e->getMessage(),
                ], 422);
            }
        }


        // =================================================
        // إذا أصبح الحساب رئيسياً
        // =================================================

        if (!$newParent) {

            $newLevel = 1;

            if (
                !ctype_digit($newCode) ||
                strlen($newCode) !== 1
            ) {

                return response()->json([
                    'success' => false,
                    'message' =>
                        'رقم الحساب الرئيسي يجب أن يتكون من خانة واحدة',
                ], 422);
            }
        }


        // =================================================
        // التأكد من عدم تكرار الرقم
        // =================================================

        $duplicate =
            CharAccount::where(
                'accCode',
                $newCode
            )
            ->where(
                'accountID',
                '!=',
                $account->accountID
            )
            ->exists();


        if ($duplicate) {

            return response()->json([
                'success' => false,
                'message' =>
                    'رقم الحساب موجود مسبقاً',
            ], 422);
        }


        // =================================================
        // البيانات القديمة
        // =================================================

        $oldLevel =
            (int) $account->accLevel;

        $oldCode =
            (string) $account->accCode;


        // =================================================
        // تنفيذ التحديث داخل Transaction
        // =================================================

        try {

            DB::transaction(function () use (
                $account,
                $request,
                $newParent,
                $newCode,
                $newLevel,
                $oldLevel,
                $oldCode,
                $parentChanged
            ) {

                // تحديث الحساب

                $account->update([

                    'accTypeID' =>
                        $request->accTypeID,

                    'accCode' =>
                        $newCode,

                    'accParent' =>
                        $newParent?->accountID,

                    'accName' =>
                        $request->accName,

                    'nature' =>
                        $request->nature,

                    'accLevel' =>
                        $newLevel,

                    'IsActive' =>
                        $request->IsActive,

                    'isPostable' =>
                        $request->isPostable,

                ]);


                // حساب فرق المستوى

                $levelDifference =
                    $newLevel - $oldLevel;


                // تحديث مستويات الأبناء

                if (
                    $levelDifference !== 0
                ) {

                    $this->updateChildrenLevels(
                        $account->accountID,
                        $levelDifference
                    );
                }


                // تحديث أكواد الأبناء

                if (
                    $parentChanged ||
                    $oldCode !== $newCode
                ) {

                    $this->updateChildrenCodes(
                        $account,
                        $oldCode,
                        $newCode
                    );
                }
            });

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' =>
                    'حدث خطأ أثناء تحديث الحساب: '
                    . $e->getMessage(),
            ], 500);
        }


        // =================================================
        // إرجاع البيانات
        // =================================================

        return response()->json([

            'success' => true,

            'message' =>
                'تم تعديل الحساب بنجاح',

            'account' =>
                $account->fresh(),

        ]);
    }


    // =====================================================
    // تحديث مستويات الأبناء
    // =====================================================

    private function updateChildrenLevels(
        int $parentId,
        int $levelDifference
    ): void {

        $children =
            CharAccount::where(
                'accParent',
                $parentId
            )->get();


        foreach ($children as $child) {

            $child->update([

                'accLevel' =>
                    ((int) $child->accLevel)
                    + $levelDifference,

            ]);


            $this->updateChildrenLevels(
                $child->accountID,
                $levelDifference
            );
        }
    }


    // =====================================================
    // تحديث أكواد جميع الحسابات الفرعية
    // =====================================================

    private function updateChildrenCodes(
        CharAccount $parent,
        string $oldCode,
        string $newCode
    ): void {

        $children =
            CharAccount::where(
                'accParent',
                $parent->accountID
            )
            ->orderBy('accountID')
            ->get();


        foreach ($children as $child) {

            $oldChildCode =
                (string) $child->accCode;


            // التأكد أن كود الابن يبدأ بكود الأب القديم

            if (
                !str_starts_with(
                    $oldChildCode,
                    $oldCode
                )
            ) {

                continue;
            }


            // الجزء الخاص بالابن

            $childSuffix =
                substr(
                    $oldChildCode,
                    strlen($oldCode)
                );


            // الكود الجديد

            $newChildCode =
                $newCode .
                $childSuffix;


            // منع تكرار الكود

            $duplicate =
                CharAccount::where(
                    'accCode',
                    $newChildCode
                )
                ->where(
                    'accountID',
                    '!=',
                    $child->accountID
                )
                ->exists();


            if ($duplicate) {

                throw new \RuntimeException(
                    'لا يمكن تحديث الحساب '
                    . $child->accName
                    . ' لأن رقم الحساب الجديد '
                    . $newChildCode
                    . ' موجود مسبقاً'
                );
            }


            // تحديث كود الابن

            $child->update([
                'accCode' =>
                    $newChildCode,
            ]);


            // تحديث أبناء الابن

            $this->updateChildrenCodes(
                $child,
                $oldChildCode,
                $newChildCode
            );
        }
    }


    // =====================================================
    // التحقق من أن الحساب من أبناء الحساب
    // =====================================================

    private function isDescendant(
        int $parentId,
        int $accountId
    ): bool {

        $current =
            CharAccount::find(
                $parentId
            );


        while ($current) {

            if (
                (int) $current->accParent ===
                $accountId
            ) {

                return true;
            }


            if (
                !$current->accParent
            ) {

                break;
            }


            $current =
                CharAccount::find(
                    $current->accParent
                );
        }


        return false;
    }


    // =====================================================
    // حذف الحساب
    // =====================================================

    public function destroy(
        CharAccount $account
    ) {

        // منع حذف الحساب الذي لديه أبناء

        $hasChildren =
            CharAccount::where(
                'accParent',
                $account->accountID
            )->exists();


        if ($hasChildren) {

            return response()->json([
                'success' => false,
                'message' =>
                    'لا يمكن حذف الحساب لأنه يحتوي على حسابات فرعية',
            ], 422);
        }


        $accountID =
            $account->accountID;


        $account->delete();


        return response()->json([

            'success' => true,

            'message' =>
                'تم حذف الحساب بنجاح',

            'accountID' =>
                $accountID,

        ]);
    }


   // =====================================================
// البحث في دليل الحسابات
// =====================================================

public function list(
Request $request
) {

// =====================================================
// جلب جميع الحسابات
// =====================================================

$accounts =
    CharAccount::query()
        ->orderBy('accCode')
        ->get();


// =====================================================
// تجهيز HTML الجدول
// =====================================================

$html =
    view(
        'setting.accounting.chartOfAccounts.display',
        compact('accounts')
    )->render();


// =====================================================
// إرجاع JSON
// =====================================================

return response()->json([

    'success' => true,

    'html' =>
        $html,

]);

}
    // =====================================================
    // جلب رقم الحساب الفرعي التالي
    // =====================================================

    public function nextCode(
        $parentId
    ) {

        // جلب الأب

        $parent =
            CharAccount::find(
                $parentId
            );


        if (!$parent) {

            return response()->json([
                'success' => false,
                'message' =>
                    'الحساب الأب غير موجود',
            ], 404);
        }


        // توليد الرقم

        try {

            $nextCode =
                $this->generateChildCode(
                    $parent
                );

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' =>
                    $e->getMessage(),
            ], 422);
        }


        return response()->json([

            'success' => true,

            'code' =>
                $nextCode,

        ]);
    }
}