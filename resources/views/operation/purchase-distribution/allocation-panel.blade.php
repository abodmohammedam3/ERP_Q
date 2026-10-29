<div class="card border-0 shadow-sm h-100">
    <div class="card-header bg-white border-0 py-2 d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <span class="bg-warning-subtle text-warning rounded-2 px-2 py-1">
                <i class="bi bi-sliders"></i>
            </span>
            <h6 class="mb-0 fw-semibold">لوحة التوزيع</h6>
        </div>
        <span class="small text-muted" id="allocationItemHint">لم يُحدد صنف</span>
    </div>
    <div class="card-body">

        <div id="allocationEmpty" class="text-center text-muted py-4">
            <i class="bi bi-arrow-up-square fs-3 d-block mb-1 opacity-50"></i>
            <div class="small">اختر صنفًا من الجدول لبدء توزيعه.</div>
        </div>

        <div id="allocationBody" class="d-none">
            {{-- بطاقة الصنف المختار --}}
            <div class="bg-primary-subtle rounded-3 p-2 mb-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-box text-primary"></i>
                        <div>
                            <div class="text-muted" style="font-size:0.7rem;">الصنف المختار</div>
                            <div class="fw-bold" id="allocItemName">—</div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-link text-muted p-0"
                            id="btnClearAllocation" title="إلغاء التحديد">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="row g-2 mt-2 text-center">
                    <div class="col">
                        <div class="bg-white rounded-2 py-1">
                            <div class="text-muted" style="font-size:0.7rem;">متاح</div>
                            <div class="fw-bold text-primary small" id="allocAvailable">—</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="bg-white rounded-2 py-1">
                            <div class="text-muted" style="font-size:0.7rem;">موزَّع</div>
                            <div class="fw-bold text-success small" id="allocAllocated">—</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="bg-white rounded-2 py-1">
                            <div class="text-muted" style="font-size:0.7rem;">متبقٍ</div>
                            <div class="fw-bold text-danger small" id="allocRemaining">—</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- جدول العملاء والكميات --}}
            <div class="table-responsive" style="max-height:200px; overflow-y:auto;">
                <table class="table table-sm table-borderless align-middle mb-0">
                    <thead class="text-muted small sticky-top bg-white">
                        <tr>
                            <th>العميل</th>
                            <th style="width:130px">الكمية</th>
                            <th style="width:44px"></th>
                        </tr>
                    </thead>
                    <tbody id="allocationTableBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>