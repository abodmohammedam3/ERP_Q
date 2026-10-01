<div class="card">

    <div class="card-header d-flex justify-content-between align-items-center">

        <span>
            <i class="bi bi-list-ul"></i>
            قائمة المخازن
        </span>

        <span
            class="badge bg-secondary"
            id="stocksCountBadge"
        >
            0
        </span>

    </div>


    <div class="card-body p-0">

        <div class="table-responsive">

            <table
                class="table table-hover table-bordered mb-0 align-middle"
                id="stocksTable"
            >

                <thead class="table-light">

                    <tr class="text-center">

                        <th style="width:60px;">
                            الرقم
                        </th>

                        <th>
                            اسم المخزن
                        </th>

                        <th style="width:150px;">
                            رقم الحساب التحليلي
                        </th>

                        <th style="width:100px;">
                            الحالة
                        </th>

                        @if($hasParent)

                            <th
                                style="width:150px;"
                                class="no-print"
                            >
                                الإجراءات
                            </th>

                        @endif

                    </tr>

                </thead>


                <tbody id="stocksTableBody">

                    <tr id="emptyStockRow">

                        <td
                            colspan="{{ $hasParent ? 5 : 4 }}"
                            class="text-center text-muted py-5"
                        >

                            <i class="bi bi-building fs-2 d-block mb-2"></i>

                            لا توجد مخازن مسجلة

                        </td>

                    </tr>

                </tbody>

            </table>

        </div>

    </div>


    <div class="card-footer d-flex justify-content-center align-items-center">

        <nav>

            <ul
                class="pagination pagination-sm mb-0"
                id="stocksPaginationList"
            ></ul>

        </nav>

    </div>

</div>


{{-- =====================================================
    قالب الصف
    مهم:
    خارج tbody حتى لا يعتبره المتصفح صفاً معروضاً
===================================================== --}}

<template id="stockRowTemplate">

    <tr
        class="stock-row text-center"
    >

        <td class="row-number"></td>

        <td class="row-name"></td>

        <td class="row-account"></td>

        <td class="row-status">

            <button
                type="button"
                class="btn btn-sm toggle-status-btn"
                onclick="toggleStockStatus(this)"
            ></button>

        </td>

        @if($hasParent)

            <td class="no-print">

                <div class="btn-action-group">

                    <button
                        type="button"
                        class="btn btn-sm btn-outline-primary"
                        onclick="editStock(this)"
                        title="تعديل"
                    >

                        <i class="bi bi-pencil"></i>

                        <span class="d-none d-md-inline">
                            تعديل
                        </span>

                    </button>


                    <button
                        type="button"
                        class="btn btn-sm btn-outline-danger"
                        onclick="deleteStock(this)"
                        title="حذف"
                    >

                        <i class="bi bi-trash"></i>

                        <span class="d-none d-md-inline">
                            حذف
                        </span>

                    </button>

                </div>

            </td>

        @endif

    </tr>

</template>
