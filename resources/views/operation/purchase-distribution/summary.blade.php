<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 py-3 d-flex align-items-center gap-2">
        <span class="bg-secondary-subtle text-secondary rounded-2 px-2 py-1">
            <i class="bi bi-calculator"></i>
        </span>
        <h6 class="mb-0 fw-semibold">ملخص التوزيع</h6>
    </div>
    <div class="card-body">

        {{-- بطاقات الإجمالي --}}
        <div class="row g-2 mb-3">
            <div class="col-6 col-md-3">
                <div class="bg-light rounded-3 p-3 text-center h-100">
                    <i class="bi bi-box text-primary fs-5"></i>
                    <div class="text-muted mt-1" style="font-size:0.72rem;">أصناف موزعة</div>
                    <div class="fw-bold fs-5" id="sumItemsCount">0</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="bg-light rounded-3 p-3 text-center h-100">
                    <i class="bi bi-people text-success fs-5"></i>
                    <div class="text-muted mt-1" style="font-size:0.72rem;">عملاء</div>
                    <div class="fw-bold fs-5" id="sumCustomersCount">0</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="bg-light rounded-3 p-3 text-center h-100">
                    <i class="bi bi-currency-dollar text-danger fs-5"></i>
                    <div class="text-muted mt-1" style="font-size:0.72rem;">إجمالي التكلفة</div>
                    <div class="fw-bold fs-5" id="sumCost">0.00</div>
                    <div class="text-muted" style="font-size:0.65rem;">ر.ي</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="bg-light rounded-3 p-3 text-center h-100">
                    <i class="bi bi-cash-coin text-success fs-5"></i>
                    <div class="text-muted mt-1" style="font-size:0.72rem;">إجمالي البيع</div>
                    <div class="fw-bold fs-5" id="sumSale">0.00</div>
                    <div class="text-muted" style="font-size:0.65rem;">ر.ي</div>
                </div>
            </div>
        </div>

        {{-- جدول تفاصيل الكميات لكل صنف --}}
        <div class="d-flex align-items-center gap-2 mb-2">
            <h6 class="small mb-0 fw-semibold">
                <i class="bi bi-list-check text-primary"></i>
                تفاصيل الكميات الموزعة لكل صنف
            </h6>
            <span class="text-muted small">(ريال يمني)</span>
        </div>
        <div class="table-responsive border rounded-3" style="max-height:280px; overflow-y:auto;">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light sticky-top">
                    <tr class="small text-muted">
                        <th class="fw-semibold">الصنف</th>
                        <th class="fw-semibold">الفاتورة</th>
                        <th class="text-end fw-semibold">الكمية الموزعة</th>
                        <th class="text-center fw-semibold">الوحدة</th>
                        <th class="text-end fw-semibold">تكلفة الوحدة</th>
                        <th class="text-end fw-semibold">سعر بيع الوحدة</th>
                        <th class="text-end fw-semibold">إجمالي التكلفة</th>
                        <th class="text-end fw-semibold">إجمالي البيع</th>
                    </tr>
                </thead>
                <tbody id="itemsBreakdownBody">
                    <tr>
                        <td colspan="8" class="text-center text-muted py-3 small">
                            <i class="bi bi-info-circle"></i>
                            لا توجد كميات موزعة بعد.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>
</div>