{{-- جدول الأصناف --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>
            <i class="bi bi-list-ul"></i> قائمة الأصناف
        </span>
        <span class="badge bg-secondary" id="itemsCountBadge">
            {{ isset($items) ? $items->count() : 0 }}
        </span>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-bordered mb-0 align-middle" id="itemsTable">
                <thead class="table-light">
                    <tr class="text-center">
                        <th style="width: 60px;">الرقم</th>
                        <th>اسم الصنف</th>
                        <th style="width: 120px;">الحالة</th>
                        <th style="width: 180px;" class="no-print">الإجراءات</th>
                    </tr>
                </thead>
                <tbody id="itemsTableBody">
                    @forelse($items ?? [] as $item)
                        <tr class="item-row text-center"
                            data-id="{{ $item->itemID }}"
                            data-name="{{ $item->itemName2 }}"
                            data-active="{{ $item->is_active ? 1 : 0 }}">

                            <td>{{ $loop->iteration }}</td>
                            <td class="row-name text-center">{{ $item->itemName2 }}</td>
                            <td class="row-status">
                                <button type="button"
                                        class="btn btn-sm {{ $item->is_active ? 'btn-success' : 'btn-secondary' }} toggle-status-btn"
                                        onclick="toggleItemStatus(this)"
                                        title="{{ $item->is_active ? 'تعطيل' : 'تفعيل' }}">
                                    {{ $item->is_active ? 'نشط' : 'غير نشط' }}
                                </button>
                            </td>
                            <td class="no-print">
                                <div class="btn-action-group">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="editItem(this)" title="تعديل">
                                        <i class="bi bi-pencil d-md-none"></i>
                                        <span class="d-none d-md-inline">تعديل</span>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteItem(this)" title="حذف">
                                        <i class="bi bi-trash d-md-none"></i>
                                        <span class="d-none d-md-inline">حذف</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr id="emptyItemRow">
                            <td colspan="4" class="text-center text-muted py-5">
                                <i class="bi bi-box-seam fs-2 d-block mb-2"></i>
                                لا توجد أصناف مسجلة
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card-footer d-flex justify-content-center align-items-center">
        <nav aria-label="Pagination">
            <ul class="pagination pagination-sm mb-0" id="itemsPaginationList"></ul>
        </nav>
    </div>
</div>

{{-- ============================================================
     قوالب JS: تُستخدم من ملف items.js لإعادة بناء الصفوف
     ============================================================ --}}

{{-- قالب صف الصنف --}}
<template id="itemRowTemplate">
    <tr class="item-row text-center">
        <td class="row-index"></td>

        <td class="row-name text-center"></td>

        <td class="row-status">
            <button type="button"
                    class="btn btn-sm toggle-status-btn"
                    onclick="toggleItemStatus(this)">
            </button>
        </td>

        <td class="no-print">
            <div class="btn-action-group">
                <button type="button"
                        class="btn btn-sm btn-outline-primary"
                        onclick="editItem(this)"
                        title="تعديل">
                    <i class="bi bi-pencil d-md-none"></i>
                    <span class="d-none d-md-inline">تعديل</span>
                </button>

                <button type="button"
                        class="btn btn-sm btn-outline-danger"
                        onclick="deleteItem(this)"
                        title="حذف">
                    <i class="bi bi-trash d-md-none"></i>
                    <span class="d-none d-md-inline">حذف</span>
                </button>
            </div>
        </td>
    </tr>
</template>

{{-- قالب صف "لا توجد بيانات" --}}
<template id="emptyItemRowTemplate">
    <tr>
        <td colspan="4" class="text-center text-muted py-5">
            <i class="bi bi-box-seam fs-2 d-block mb-2"></i>
            لا توجد أصناف مسجلة
        </td>
    </tr>
</template>