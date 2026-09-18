<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="jeTable">
            <thead class="table-light">
                <tr>
                    <th width="50">#</th>
                    <th width="100">رقم القيد</th>
                    <th width="110">التاريخ</th>
                    <th width="140">نوع المستند</th>
                    <th width="130">رقم المستند</th>
                    <th>البيان</th>
                    <th width="130">مدين</th>
                    <th width="130">دائن</th>
                    <th width="80">تفاصيل</th>
                </tr>
            </thead>
            <tbody id="jeTableBody">
                <tr id="jeNoDataRow">
                    <td colspan="9" class="text-center text-muted py-4">
                        لا توجد قيود
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- الترقيم --}}
    <div id="jePagination" class="p-3 border-top"></div>
</div>

{{-- ═══════════════════════════════════════════════════════ --}}
{{--  قالب صف الجدول                                        --}}
{{-- ═══════════════════════════════════════════════════════ --}}
<template id="jeRowTemplate">
    <tr>
        <td class="je-row-index"></td>
        <td class="je-row-no fw-bold text-primary"></td>
        <td class="je-row-date"></td>
        <td class="je-row-doc-type"></td>
        <td class="je-row-doc-number"></td>
        <td class="je-row-desc"></td>
        <td class="je-row-debit text-end"></td>
        <td class="je-row-credit text-end"></td>
        <td class="text-center">
            <button type="button"
                    class="btn btn-sm btn-outline-primary je-btn-show"
                    title="عرض التفاصيل">
                <i class="bi bi-eye"></i>
            </button>
        </td>
    </tr>
</template>

{{-- ═══════════════════════════════════════════════════════ --}}
{{--  قوالب الترقيم                                        --}}
{{-- ═══════════════════════════════════════════════════════ --}}

<template id="jePaginationInfoTemplate">
    <small class="text-muted pg-info"></small>
</template>

<template id="jePaginationListTemplate">
    <nav>
        <ul class="pagination pagination-sm mb-0 pg-list"></ul>
    </nav>
</template>

<template id="jePaginationItemTemplate">
    <li class="page-item pg-item">
        <a class="page-link pg-btn" href="#">
            <i class="pg-icon"></i>
        </a>
    </li>
</template>