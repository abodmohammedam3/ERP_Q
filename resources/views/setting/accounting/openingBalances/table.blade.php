{{-- تنسيق خلايا المبالغ والملاحظات --}}
<style>
    /* ─── خلايا المبالغ ─── */
    .amount-foreign {
        font-size: 0.72rem;
        color: #6c757d;
        direction: ltr;
        text-align: right;
        line-height: 1.2;
        font-weight: 400;
    }

    .amount-local {
        font-size: 0.95rem;
        font-weight: 600;
        color: #212529;
        direction: ltr;
        text-align: right;
        line-height: 1.3;
    }

    .amount-single {
        font-size: 0.95rem;
        font-weight: 600;
        color: #212529;
        direction: ltr;
        text-align: right;
    }

    /* ─── الملاحظات: نص مختصر مع نقاط ─── */
    .row-notes {
        max-width: 150px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        cursor: help;
        direction: rtl;
        text-align: right;
    }

    /* ─── الترقيم ─── */
    #obPagination .pagination {
        margin: 0;
    }

    #obPagination .page-link {
        padding: 4px 10px;
        font-size: 0.85rem;
        color: #0d6efd;
        cursor: pointer;
    }

    #obPagination .page-item.active .page-link {
        background-color: #0d6efd;
        border-color: #0d6efd;
        color: #fff;
    }

    #obPagination .page-item.disabled .page-link {
        color: #adb5bd;
        pointer-events: none;
    }
</style>

{{-- الجدول --}}
<div class="table-responsive">
    <table class="table table-bordered table-hover align-middle" id="obTable">
        <thead class="table-light">
            <tr>
                <th width="50">#</th>
                <th>رقم الحساب</th>
                <th>اسم الحساب</th>
                <th width="80">العملة</th>
                <th width="100">سعر الصرف</th>
                <th width="150">مدين</th>
                <th width="150">دائن</th>
                <th width="170">الرصيد</th>
                <th width="150">ملاحظات</th>
                <th width="120">إجراءات</th>
            </tr>
        </thead>
        <tbody id="obTableBody">
            <tr id="noDataRow">
                <td colspan="10" class="text-center text-muted py-4">
                    لا توجد بيانات
                </td>
            </tr>
        </tbody>
    </table>
</div>

{{-- حاوية الترقيم --}}
<div id="obPagination"></div>

{{-- ═══════════════════════════════════════════════════════ --}}
{{--  قالب صف الجدول                                        --}}
{{-- ═══════════════════════════════════════════════════════ --}}
<template id="obRowTemplate">
    <tr>
        <td class="row-index"></td>
        <td class="row-account-code"></td>
        <td class="row-account-name"></td>
        <td class="row-currency"></td>
        <td class="row-rate"></td>

        <td class="row-debit">
            <div class="amount-foreign"></div>
            <div class="amount-local"></div>
        </td>

        <td class="row-credit">
            <div class="amount-foreign"></div>
            <div class="amount-local"></div>
        </td>

        <td class="row-net">
            <div class="amount-foreign"></div>
            <div class="amount-local"></div>
        </td>

        <td class="row-notes"></td>

        <td>
            <button type="button"
                    class="btn btn-sm btn-primary btn-edit-row"
                    title="تعديل">
                <i class="bi bi-pencil"></i>
            </button>
            <button type="button"
                    class="btn btn-sm btn-danger btn-delete-row"
                    title="حذف">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    </tr>
</template>