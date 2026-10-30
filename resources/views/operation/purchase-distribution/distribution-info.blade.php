<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 py-2">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="d-flex align-items-center gap-2">
                <span class="bg-primary-subtle text-primary rounded-2 px-2 py-1">
                    <i class="bi bi-receipt"></i>
                </span>
                <h6 class="mb-0 fw-semibold">الفواتير المضافة</h6>
            </div>
        </div>

        {{-- شريط التبويبات --}}
        <div id="invoicesTabsBox" class="d-flex flex-wrap gap-1 align-items-center">
            <div class="text-muted small text-center w-100 py-2">
                <i class="bi bi-inbox fs-4 d-block mb-1 opacity-50"></i>
                لم تُضف فواتير بعد — اضغط «إضافة فاتورة» للبدء.
            </div>
        </div>
    </div>

    <div class="card-body pt-2">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">
                    <i class="bi bi-calendar-event"></i> تاريخ التوزيع
                </label>
                <input type="date" class="form-control form-control-sm" id="distributionDate">
            </div>
            <div class="col-md-9">
                <label class="form-label small text-muted mb-1">
                    <i class="bi bi-chat-text"></i> ملاحظات
                </label>
                <input type="text" class="form-control form-control-sm" id="distributionNotes"
                       placeholder="اختياري">
            </div>
        </div>
    </div>
</div>