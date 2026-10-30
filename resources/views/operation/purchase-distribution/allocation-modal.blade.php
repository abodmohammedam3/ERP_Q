<style>
    /* إخفاء أسهم input[type=number] داخل المودال فقط */
    #allocationModal input.alloc-input::-webkit-outer-spin-button,
    #allocationModal input.alloc-input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    #allocationModal input.alloc-input {
        -moz-appearance: textfield;
        appearance: textfield;
    }
</style>

<div class="modal fade" id="allocationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">

            <div class="modal-header bg-white border-0 py-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="bg-warning-subtle text-warning rounded-2 px-2 py-1">
                        <i class="bi bi-sliders"></i>
                    </span>
                    <div>
                        <h6 class="modal-title mb-0 fw-semibold" id="allocItemName">توزيع الصنف</h6>
                        <div class="text-muted small" id="allocInvoiceNumber">—</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <div class="row g-2 mb-3 text-center">
                    <div class="col">
                        <div class="bg-light rounded-3 p-2">
                            <div class="text-muted" style="font-size:0.72rem;">متاح</div>
                            <div class="fw-bold text-primary" id="allocAvailable">—</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="bg-light rounded-3 p-2">
                            <div class="text-muted" style="font-size:0.72rem;">موزَّع</div>
                            <div class="fw-bold text-success" id="allocAllocated">—</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="bg-light rounded-3 p-2">
                            <div class="text-muted" style="font-size:0.72rem;">متبقٍ</div>
                            <div class="fw-bold text-danger" id="allocRemaining">—</div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0 fw-semibold small">العملاء الموزَّع لهم</h6>
                    <button type="button" class="btn btn-sm btn-success" id="btnAddCustomerToItem">
                        <i class="bi bi-plus-lg"></i> إضافة عميل
                    </button>
                </div>

                <div class="table-responsive border rounded-3" style="max-height:300px; overflow-y:auto;">
                    <table class="table table-sm table-borderless align-middle mb-0">
                        <thead class="table-light sticky-top">
                            <tr class="small text-muted">
                                <th>العميل</th>
                                <th style="width:140px">الكمية</th>
                                <th style="width:44px"></th>
                            </tr>
                        </thead>
                        <tbody id="allocationTableBody"></tbody>
                    </table>
                </div>

            </div>

            <div class="modal-footer bg-white border-0 d-flex justify-content-between flex-wrap gap-2">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    إغلاق
                </button>

                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="btnPrevItem">
                        <i class="bi bi-chevron-right"></i> السابق
                    </button>
                    <span class="badge bg-light text-dark border" id="allocItemCounter">—</span>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="btnNextItem">
                        التالي <i class="bi bi-chevron-left"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>