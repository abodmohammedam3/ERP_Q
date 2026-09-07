<!-- بطاقة جدول الحسابات -->
<div class="card" id="accountsTable">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>رقم الحساب التحليلي</th>
                        <th>اسم الحساب</th>
                        <th>طبيعة الحساب</th>
                        <th>الحساب الأب</th>
                        <th>الحالة</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody id="accountsTreeBody">

                    @php
                        /*
                        |--------------------------------------------------------------------------
                        | ترتيب الحسابات كشجرة
                        |--------------------------------------------------------------------------
                        */

                        $accountsByParent = $accounts->groupBy(function ($account) {
                            return $account->accParent ?? 0;
                        });

                        $orderedAccounts = collect();

                        $addChildren = function ($parentId) use (
                            &$addChildren,
                            &$orderedAccounts,
                            $accountsByParent
                        ) {
                            $children = $accountsByParent->get($parentId, collect());

                            foreach ($children as $child) {

                                $orderedAccounts->push($child);

                                // إضافة أبناء هذا الحساب تحته مباشرة
                                $addChildren($child->accountID);
                            }
                        };

                        // الحسابات الرئيسية أولاً
                        $addChildren(0);
                    @endphp


                    @forelse($orderedAccounts as $index => $account)

                        @php
                            $hasChildren = $accounts->contains(
                                'accParent',
                                $account->accountID
                            );
                        @endphp

                        <tr
                            class="account-row"
                            data-id="{{ $account->accountID }}"
                            data-parent="{{ $account->accParent ?? '' }}"
                            data-level="{{ $account->accLevel }}"
                        >

                            {{-- # --}}
                            <td>
                                {{ $index + 1 }}
                            </td>


                            {{-- رقم الحساب --}}
                            <td>

                                <span
                                    class="tree-indent"
                                    style="
                                        margin-right:
                                        {{ ($account->accLevel - 1) * 25 }}px;
                                    "
                                >

                                    @if($hasChildren)

                                        <button
                                            type="button"
                                            class="btn btn-sm btn-link tree-toggle"
                                            data-id="{{ $account->accountID }}"
                                            title="فتح / إغلاق"
                                        >
                                            <i class="bi bi-chevron-left"></i>
                                        </button>

                                    @else

                                        <span
                                            class="tree-empty-space"
                                            style="
                                                display:inline-block;
                                                width:32px;
                                            "
                                        ></span>

                                    @endif

                                    {{ $account->accCode }}

                                </span>

                            </td>


                            {{-- اسم الحساب --}}
                            <td>
                                {{ $account->accName }}
                            </td>


                            {{-- طبيعة الحساب --}}
                            <td>

                                @if($account->nature == 0)

                                    <span class="badge bg-info">
                                        مدين
                                    </span>

                                @else

                                    <span class="badge bg-success">
                                        دائن
                                    </span>

                                @endif

                            </td>


                            {{-- الحساب الأب --}}
                            <td>

                                @php

                                    $parentAccount = $accounts->firstWhere(
                                        'accountID',
                                        $account->accParent
                                    );

                                @endphp

                                {{ $parentAccount?->accName ?? '--' }}

                            </td>


                            {{-- الحالة --}}
                            <td>

                                @if($account->IsActive == 1)

                                    <span class="badge bg-primary">
                                        نشط
                                    </span>

                                @else

                                    <span class="badge bg-secondary">
                                        غير نشط
                                    </span>

                                @endif

                            </td>


                            {{-- الإجراءات --}}
                            <td>

                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-primary me-1 edit-account"
                                    data-id="{{ $account->accountID }}"
                                >
                                    <i class="bi bi-pencil"></i>
                                    تعديل
                                </button>


                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-danger delete-account"
                                    data-id="{{ $account->accountID }}"
                                >
                                    <i class="bi bi-trash"></i>
                                    حذف
                                </button>

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="7"
                                class="text-center py-4 text-muted"
                            >
                                لا توجد حسابات
                            </td>

                        </tr>

                    @endforelse


                </tbody>
            </table>
        </div>
    </div>
    <!-- تذييل الجدول (ترقيم الصفحات) -->
    <div
        class="card-footer d-flex flex-wrap justify-content-between align-items-center"
        id="accountsPagination"
    >
        <span
            class="text-muted small"
            id="accountsPaginationInfo"
        >
            عرض 0-0 من 0 حساب
        </span>

        <nav>
            <ul
                class="pagination pagination-sm mb-0"
                id="accountsPaginationList"
            >
            </ul>
        </nav>
    </div>
</div>