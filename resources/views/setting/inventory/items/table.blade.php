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
                        <th>الرقم</th>
                        <th>اسم الصنف</th>
                        <th class="no-print" style="width: 180px;">الإجراءات</th>
                    </tr>
                </thead>
                <tbody id="itemsTableBody">

                    @forelse($items ?? [] as $item)
                        <tr class="item-row text-center">
                            <td class="row-id">{{ $item->itemID }}</td>
                            <td class="row-name">{{ $item->itemName2 }}</td>
                            <td class="no-print">
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="editItem(this)">
                                    <i class="bi bi-pencil"></i> تعديل
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteItem(this)">
                                    <i class="bi bi-trash"></i> حذف
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr id="emptyItemRow">
                            <td colspan="3" class="text-center text-muted py-5">
                                <i class="bi bi-box-seam fs-2 d-block mb-2"></i>
                                لا توجد أصناف مسجلة
                            </td>
                        </tr>
                    @endforelse

                </tbody>
            </table>
        </div>
    </div>
</div>
