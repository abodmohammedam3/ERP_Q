<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 py-2 d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <span class="bg-info-subtle text-info rounded-2 px-2 py-1">
                <i class="bi bi-list-ul"></i>
            </span>
            <h6 class="mb-0 fw-semibold">
                أصناف الفاتورة: <span class="text-primary" id="activeInvoiceLabel">—</span>
            </h6>
        </div>
        <div class="input-group input-group-sm" style="max-width:220px;">
            <span class="input-group-text bg-white border-end-0">
                <i class="bi bi-search text-muted"></i>
            </span>
            <input type="text" class="form-control border-start-0 ps-0"
                   id="itemsSearch" placeholder="بحث في الأصناف...">
        </div>
    </div>
    <div class="card-body p-0">
        <div id="itemsEmpty" class="text-center text-muted py-4">
            <i class="bi bi-inbox fs-3 d-block mb-1 opacity-50"></i>
            <div class="small">أضف فاتورة لعرض أصنافها.</div>
        </div>
        <div class="table-responsive" id="itemsTableWrapper"
             style="display:none; max-height:280px; overflow-y:auto;">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light sticky-top">
                    <tr class="small text-muted">
                        <th style="width:36px"></th>
                        <th class="fw-semibold">الصنف</th>
                        <th class="fw-semibold">النوع</th>
                        <th class="fw-semibold">الوحدة</th>
                        <th class="fw-semibold">الرمز</th>
                        <th class="text-end fw-semibold">الكمية</th>
                        <th class="text-end fw-semibold">المتبقي</th>
                        <th class="text-end fw-semibold">تكلفة الوحدة</th>
                        <th class="text-end fw-semibold">سعر بيع الوحدة</th>
                    </tr>
                </thead>
                <tbody id="itemsTableBody"></tbody>
            </table>
        </div>
    </div>
</div>