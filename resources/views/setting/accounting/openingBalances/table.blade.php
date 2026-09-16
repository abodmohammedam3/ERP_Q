<div class="table-responsive">
    <table class="table table-bordered table-hover align-middle" id="obTable">
        <thead class="table-light">
            <tr>
                <th width="50">#</th>
                <th>رقم الحساب</th>
                <th>اسم الحساب</th>
                <th width="150">مدين</th>
                <th width="150">دائن</th>
                <th width="150">الرصيد</th>
                <th>ملاحظات</th>
                <th width="120">إجراءات</th>
            </tr>
        </thead>
        <tbody id="obTableBody">
            {{-- صف "لا توجد بيانات" --}}
            <tr id="noDataRow">
                <td colspan="8" class="text-center text-muted py-4">
                    لا توجد بيانات
                </td>
            </tr>
        </tbody>
    </table>
</div>

{{-- قالب صف الجدول (يُستخدم من الجافاسكربت) --}}
<template id="obRowTemplate">
    <tr>
        <td class="row-index"></td>
        <td class="row-account-code"></td>
        <td class="row-account-name"></td>
        <td class="row-debit"></td>
        <td class="row-credit"></td>
        <td class="row-net"></td>
        <td class="row-notes"></td>
        <td>
            <button type="button"
                    class="btn btn-sm btn-warning btn-edit-row">
                <i class="fas fa-edit"></i>
            </button>
            <button type="button"
                    class="btn btn-sm btn-danger btn-delete-row">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    </tr>
</template>