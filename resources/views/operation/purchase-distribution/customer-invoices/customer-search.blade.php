<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-8">
                <label class="form-label small text-muted mb-1">
                    <i class="bi bi-person"></i> العميل
                </label>
                <div class="border rounded-3 p-2 bg-light d-flex align-items-center gap-2"
                     id="customerPickerTrigger"
                     style="cursor:pointer; min-height:42px;">
                    <i class="bi bi-search text-muted"></i>
                    <span class="text-muted small" id="customerPickerLabel">اضغط لاختيار عميل...</span>
                </div>
            </div>
            <div class="col-md-4 text-md-end">
                <button class="btn btn-sm btn-outline-secondary" id="btnClearCustomer" disabled>
                    <i class="bi bi-x-circle"></i> إلغاء التحديد
                </button>
            </div>
        </div>
    </div>
</div>