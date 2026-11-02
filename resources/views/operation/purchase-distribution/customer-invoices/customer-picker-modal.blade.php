<div class="modal fade" id="customerPickerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content border-0 shadow">

            <div class="modal-header bg-white border-0 py-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="bg-success-subtle text-success rounded-2 px-2 py-1">
                        <i class="bi bi-person-circle"></i>
                    </span>
                    <h6 class="modal-title mb-0 fw-semibold">اختيار عميل من التوزيع</h6>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <div class="input-group input-group-sm mb-3">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" id="custSearchInput" class="form-control" placeholder="ابحث بالاسم أو الهاتف...">
                    <button type="button" class="btn btn-outline-secondary" id="custSearchClearBtn" title="مسح">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                <div id="custLoading" class="text-center py-4 d-none">
                    <div class="spinner-border spinner-border-sm text-primary"></div>
                    <span class="small ms-2 text-muted">جارٍ التحميل...</span>
                </div>
                <div id="custEmpty" class="alert alert-warning border-0 py-2 px-3 small mb-2 d-none">
                    <i class="bi bi-info-circle"></i> لا يوجد عملاء في هذا التوزيع.
                </div>
                <div id="custError" class="alert alert-danger border-0 py-2 px-3 small mb-2 d-none"></div>

                <div class="table-responsive border rounded-3" style="max-height:420px; overflow-y:auto;">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light sticky-top">
                            <tr class="small text-muted">
                                <th style="width:40px">#</th>
                                <th class="fw-semibold">العميل</th>
                                <th class="text-center fw-semibold">صفوف</th>
                                <th class="text-end fw-semibold">إجمالي البيع</th>
                            </tr>
                        </thead>
                        <tbody id="custTbodyForInvoice"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>