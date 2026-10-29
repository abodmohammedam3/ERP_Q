<div class="modal fade" id="invoicePickerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content border-0 shadow">

            <div class="modal-header bg-white border-0 py-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="bg-primary-subtle text-primary rounded-2 px-2 py-1">
                        <i class="bi bi-receipt"></i>
                    </span>
                    <h6 class="modal-title mb-0 fw-semibold">اختيار فاتورة شراء</h6>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="row g-2 mb-3 align-items-end">
                    <div class="col-4">
                        <label class="form-label small text-muted mb-1">
                            <i class="bi bi-calendar-event"></i> التاريخ
                        </label>
                        <input type="date" class="form-control form-control-sm" id="invoiceFilterDate">
                    </div>
                    <div class="col-5">
                        <label class="form-label small text-muted mb-1">
                            <i class="bi bi-truck"></i> اسم المورد
                        </label>
                        <input type="text" class="form-control form-control-sm"
                               id="invoiceFilterSupplier" placeholder="بحث تلقائي...">
                    </div>
                    <div class="col-3">
                        <button type="button" class="btn btn-sm btn-outline-secondary w-100"
                                id="invoiceClearBtn">
                            <i class="bi bi-x-circle"></i> تفريغ
                        </button>
                    </div>
                </div>

                <div id="invoiceLoading" class="text-center py-4 d-none">
                    <div class="spinner-border spinner-border-sm text-primary"></div>
                    <span class="small ms-2 text-muted">جارٍ التحميل...</span>
                </div>
                <div id="invoiceEmpty" class="alert alert-warning border-0 py-2 px-3 small mb-2 d-none">
                    <i class="bi bi-info-circle"></i> لا توجد فواتير مطابقة.
                </div>
                <div id="invoiceError" class="alert alert-danger border-0 py-2 px-3 small mb-2 d-none"></div>

                <div class="table-responsive rounded-3 border" id="invoiceTableWrapper"
                     style="max-height:420px; overflow-y:auto;">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light sticky-top">
                            <tr class="small text-muted">
                                <th style="width:40px">#</th>
                                <th class="fw-semibold">رقم الفاتورة</th>
                                <th class="fw-semibold">التاريخ</th>
                                <th class="fw-semibold">المورد</th>
                                <th class="text-end fw-semibold">الإجمالي</th>
                            </tr>
                        </thead>
                        <tbody id="invoiceTableBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>