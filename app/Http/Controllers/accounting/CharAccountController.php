<?php

namespace App\Http\Controllers\accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\CharAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CharAccountController extends Controller
{
    // =====================================================
    // عرض صفحة دليل الحسابات
    // =====================================================

    public function index()
    {
        return view(
            'setting.accounting.chartOfAccounts.index'
        );
    }


    // =====================================================
    // جلب الحساب للتعديل
    // =====================================================

    public function edit(CharAccount $account)
    {
        return response()->json([

            'success' => true,

            'account' => [
                'accountID'  => $account->accountID,
                'accTypeID'  => $account->accTypeID,
                'accCode'    => $account->accCode,
                'accParent'  => $account->accParent,
                'accName'    => $account->accName,
                'nature'     => $account->nature,
                'accLevel'   => $account->accLevel,
                'IsActive'   => $account->IsActive,
                'isPostable' => $account->isPostable,
                'is_system'  => $account->is_system,
                'system_key' => $account->system_key,
            ],

        ]);
    }


    // =====================================================
    // إضافة حساب
    // =====================================================

    public function store(Request $request)
    {
        $request->validate(
            [
                'accTypeID'  => 'required|integer',
                'accParent'  => 'nullable|integer',
                'accCode'    => 'nullable|string',
                'accName'    => 'required|string',
                'nature'     => 'required|integer',
                'IsActive'   => 'required|integer|in:0,1',
                'isPostable' => 'required|integer|in:0,1',
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

            $parent = CharAccount::find(
                (int) $request->accParent
            );

            if (!$parent) {

                return response()->json([
                    'success' => false,
                    'message' => 'الحساب الأب غير موجود',
                ], 422);
            }
        }


        // =================================================
        // تحديد مستوى الحساب
        // =================================================

        $accLevel = $parent
            ? ((int) $parent->accLevel + 1)
            : 1;


        // =================================================
        // تحديد رقم الحساب
        // =================================================

        if (!$parent) {

            // الحساب الرئيسي

            if (!$request->filled('accCode')) {

                return response()->json([
                    'success' => false,
                    'message' =>
                        'رقم الحساب مطلوب للحساب الرئيسي',
                ], 422);
            }

            $accCode = trim(
                (string) $request->accCode
            );


            if (!ctype_digit($accCode)) {

                return response()->json([
                    'success' => false,
                    'message' =>
                        'رقم الحساب يجب أن يكون رقماً صحيحاً',
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

            // الحساب الفرعي

            try {

                $accCode =
                    $this->generateChildCode(
                        $parent
                    );

            } catch (\Throwable $e) {

                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }
        }


        // =================================================
        // منع تكرار رقم الحساب
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

        $account = CharAccount::create([

            'accTypeID' =>
                (int) $request->accTypeID,

            'accCode' =>
                $accCode,

            'accParent' =>
                $parent?->accountID,

            'accName' =>
                trim($request->accName),

            'nature' =>
                (int) $request->nature,

            'accLevel' =>
                $accLevel,

            'IsActive' =>
                (int) $request->IsActive,

            'isPostable' =>
                (int) $request->isPostable,

            'is_system' =>
                0,

            'system_key' =>
                null,
        ]);


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


        // المستوى الثاني خانة واحدة
        // المستويات التالية خانتان

        $segmentLength =
            $childLevel === 2
                ? 1
                : 2;


        // =================================================
        // جلب أبناء الحساب
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
        // حماية الحسابات النظامية
        // =================================================

        if ((int) $account->is_system === 1) {

            return response()->json([
                'success' => false,
                'message' =>
                    'لا يمكن تعديل حساب نظامي',
            ], 403);
        }


        // =================================================
        // التحقق من البيانات
        // =================================================

        $request->validate(
            [
                'accTypeID' =>
                    'required|integer',

                'accCode' =>
                    'required|string|unique:characcount,accCode,'
                    . $account->accountID
                    . ',accountID',

                'accParent' =>
                    'nullable|integer',

                'accName' =>
                    'required|string',

                'nature' => 'required|integer|in:0,1',

                'IsActive' =>
                    'required|integer|in:0,1',

                'isPostable' =>
                    'required|integer|in:0,1',
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
        // معرفة تغير الأب
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

        $newLevel =
            $newParent
                ? ((int) $newParent->accLevel + 1)
                : 1;


        // =================================================
        // الرقم الجديد
        // =================================================

        $newCode =
            trim(
                (string) $request->accCode
            );


        // إذا تغير الأب يتم توليد كود جديد

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
                    'message' => $e->getMessage(),
                ], 422);
            }
        }


        // =================================================
        // إذا أصبح حساباً رئيسياً
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
        // منع التكرار
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
        // تنفيذ التحديث
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

                $account->update([

                    'accTypeID' =>
                        (int) $request->accTypeID,

                    'accCode' =>
                        $newCode,

                    'accParent' =>
                        $newParent?->accountID,

                    'accName' =>
                        trim($request->accName),

                    'nature' =>
                        (int) $request->nature,

                    'accLevel' =>
                        $newLevel,

                    'IsActive' =>
                        (int) $request->IsActive,

                    'isPostable' =>
                        (int) $request->isPostable,

                ]);


                // =================================================
                // تحديث مستويات الأبناء
                // =================================================

                $levelDifference =
                    $newLevel - $oldLevel;


                if (
                    $levelDifference !== 0
                ) {

                    $this->updateChildrenLevels(
                        $account->accountID,
                        $levelDifference
                    );
                }


                // =================================================
                // تحديث أكواد الأبناء
                // =================================================

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
    // تحديث أكواد الحسابات الفرعية
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


            if (
                !str_starts_with(
                    $oldChildCode,
                    $oldCode
                )
            ) {
                continue;
            }


            $childSuffix =
                substr(
                    $oldChildCode,
                    strlen($oldCode)
                );


            $newChildCode =
                $newCode . $childSuffix;


            // منع التكرار

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


            $child->update([
                'accCode' =>
                    $newChildCode,
            ]);


            $this->updateChildrenCodes(
                $child,
                $oldChildCode,
                $newChildCode
            );
        }
    }


    // =====================================================
    // التحقق من أن الحساب من أبناء حساب معين
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

        // حماية الحسابات النظامية

        if ((int) $account->is_system === 1) {

            return response()->json([
                'success' => false,
                'message' =>
                    'لا يمكن حذف حساب نظامي',
            ], 403);
        }


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


        try {

            $account->delete();

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' =>
                    'لا يمكن حذف الحساب: '
                    . $e->getMessage(),
            ], 500);
        }


        return response()->json([

            'success' => true,

            'message' =>
                'تم حذف الحساب بنجاح',

            'accountID' =>
                $accountID,

        ]);
    }

// =====================================================
// جلب شجرة الحسابات التجميعية
// =====================================================

public function tree(Request $request)
{
    $query = CharAccount::query()

        // الحسابات التجميعية فقط
        ->where('isPostable', 0)

        // المستوى الأول والثاني وما تحته
        ->orderBy('accCode');

    // =================================================
    // البحث برقم الحساب
    // =================================================

    if ($request->filled('search_code')) {

        $query->where(
            'accCode',
            'like',
            '%' . trim($request->search_code) . '%'
        );
    }

    // =================================================
    // البحث باسم الحساب
    // =================================================

    if ($request->filled('search_name')) {

        $query->where(
            'accName',
            'like',
            '%' . trim($request->search_name) . '%'
        );
    }

    // =================================================
    // البحث حسب الطبيعة
    // =================================================

    if ($request->filled('search_nature')) {

        $query->where(
            'nature',
            (int) $request->search_nature
        );
    }

    // =================================================
    // جلب الحسابات
    // =================================================

    $accounts = $query
        ->get([
            'accountID',
            'accCode',
            'accName',
            'accParent',
            'accLevel',
            'accTypeID',
            'nature',
            'isPostable',
            'IsActive',
            'is_system',
            'system_key',
        ]);
        $analyticalParentIds = CharAccount::query()
    ->where('isPostable', 1)
    ->whereNotNull('accParent')
    ->whereIn(
        'accParent',
        $accounts->pluck('accountID')
    )
    ->pluck('accParent')
    ->unique();

$accounts->each(function ($account) use ($analyticalParentIds) {

    $account->hasAnalytical =
        $analyticalParentIds->contains(
            $account->accountID
        );

});

    return response()->json([

        'success' => true,

        'accounts' => $accounts,

    ]);
}


// =====================================================
// جلب الحسابات التحليلية التابعة لحساب تجميعي
// =====================================================

public function analyticalAccounts(
    Request $request,
    CharAccount $account
) {

    // =================================================
    // التأكد أن الحساب الأب تجميعي
    // =================================================

    if ((int) $account->isPostable === 1) {

        return response()->json([

            'success' => false,

            'message' =>
                'لا يمكن عرض الحسابات التحليلية لحساب تحليلي',

        ], 422);
    }

    // =================================================
    // الحسابات التحليلية التابعة مباشرة
    // =================================================

    $query = CharAccount::query()

        ->where(
            'accParent',
            $account->accountID
        )

        ->where(
            'isPostable',
            1
        );

    // =================================================
    // البحث برقم الحساب
    // =================================================

    if ($request->filled('search_code')) {

        $query->where(
            'accCode',
            'like',
            '%' . trim($request->search_code) . '%'
        );
    }

    // =================================================
    // البحث باسم الحساب
    // =================================================

    if ($request->filled('search_name')) {

        $query->where(
            'accName',
            'like',
            '%' . trim($request->search_name) . '%'
        );
    }

    // =================================================
    // البحث حسب الطبيعة
    // =================================================

    if ($request->filled('search_nature')) {

        $query->where(
            'nature',
            (int) $request->search_nature
        );
    }

    // =================================================
    // Pagination
    // =================================================

    $accounts = $query

        ->orderBy('accCode')

        ->paginate(
            $request->integer('per_page', 10)
        );

    return response()->json([

        'success' => true,

        'parent' => [

            'accountID' =>
                $account->accountID,

            'accCode' =>
                $account->accCode,

            'accName' =>
                $account->accName,

        ],

        'accounts' => $accounts,

    ]);
}


    // =====================================================
    // جلب رقم الحساب الفرعي التالي
    // =====================================================

    public function nextCode(
        int|string $parentId
    ) {

        $parent =
            CharAccount::find(
                (int) $parentId
            );


        if (!$parent) {

            return response()->json([
                'success' => false,
                'message' =>
                    'الحساب الأب غير موجود',
            ], 404);
        }


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

