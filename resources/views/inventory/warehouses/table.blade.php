{{-- جدول المخازن --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>
            <i class="bi bi-list-ul"></i> قائمة المخازن
        </span>
        <span class="badge bg-secondary" id="stocksCountBadge">
            {{ isset($stocks) ? $stocks->count() : 0 }}
        </span>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-bordered mb-0 align-middle" id="stocksTable">
                <thead class="table-light">
                    <tr>
                        <th class="text-center">#</th>
                        <th>اسم المخزن</th>
                        <th>الحساب المرتبط</th>
                        <th class="text-center" style="width: 180px;">الإجراءات</th>
                    </tr>
                </thead>
                <tbody id="stocksTableBody">

                    @forelse($stocks ?? [] as $stock)
                        <tr class="stock-row">
                            <td class="text-center row-id">{{ $stock->StockID }}</td>
                            <td class="row-name">{{ $stock->StockName2 }}</td>
                            <td class="row-account">{{ $stock->account->accName ?? 'غير مرتبط' }}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="editStock(this)" title="تعديل">
                                    <i class="bi bi-pencil"></i> تعديل
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteStock(this)" title="حذف">
                                    <i class="bi bi-trash"></i> حذف
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr id="emptyStockRow">
                            <td colspan="4" class="text-center text-muted py-5">
                                <i class="bi bi-building fs-2 d-block mb-2"></i>
                                لا توجد مخازن مسجلة
                            </td>
                        </tr>
                    @endforelse

                </tbody>
            </table>
        </div>
    </div>
</div>
