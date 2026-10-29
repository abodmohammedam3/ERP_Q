<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 py-2 d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <span class="bg-success-subtle text-success rounded-2 px-2 py-1">
                <i class="bi bi-people"></i>
            </span>
            <h6 class="mb-0 fw-semibold">العملاء المشاركون</h6>
        </div>
        <button type="button" class="btn btn-sm btn-success" id="btnAddCustomer">
            <i class="bi bi-plus-lg"></i> إضافة عميل
        </button>
    </div>
    <div class="card-body">
        <div class="border rounded-3 p-2 bg-light mb-2 d-flex align-items-center gap-2"
             id="customerPickerTrigger" style="cursor:pointer;">
            <i class="bi bi-search text-muted"></i>
            <span class="text-muted small">اضغط لاختيار عميل...</span>
        </div>

        <ul class="list-group list-group-flush" id="selectedCustomersList"
            style="max-height:220px; overflow-y:auto;">
            <li class="list-group-item text-muted text-center small py-3 border-0"
                id="customersEmpty">
                <i class="bi bi-person-plus fs-3 d-block mb-1 opacity-50"></i>
                لم يُضف عملاء بعد.
            </li>
        </ul>
    </div>
</div>