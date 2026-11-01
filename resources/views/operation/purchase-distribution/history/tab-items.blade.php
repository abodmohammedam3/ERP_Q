<div id="tab-items" class="d-none">

    {{-- البحث --}}
    <div class="row g-2 mb-3 align-items-end">
        <div class="col-md-6">
            <label class="form-label small text-muted mb-1">بحث عن صنف (الاسم أو الكود)</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white border-end-0">
                    <i class="bi bi-search text-muted"></i>
                </span>
                <input type="text" class="form-control border-start-0 ps-0"
                       id="itemSearch" placeholder="اكتب اسم الصنف أو الكود...">
            </div>
            <div id="itemResults"
                 class="list-group list-group-flush border rounded-3 mt-1"
                 style="max-height:200px; overflow-y:auto; display:none;"></div>
        </div>
    </div>

    {{-- ملخص الصنف --}}
    <div id="itemSummaryBox" class="d-none">
        <div class="bg-warning-subtle rounded-3 p-3 mb-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="text-muted small">الصنف</div>
                    <div class="fw-bold fs-6" id="itmName">—</div>
                </div>
            </div>
            <div class="row g-2 mt-2 text-center">
                <div class="col">
                    <div class="bg-white rounded-2 py-1">
                        <div class="text-muted" style="font-size:0.72rem;">الكمية الأصلية</div>
                        <div class="fw-bold text-primary" id="itmOriginal">0</div>
                    </div>
                </div>
                <div class="col">
                    <div class="bg-white rounded-2 py-1">
                        <div class="text-muted" style="font-size:0.72rem;">الموزّع</div>
                        <div class="fw-bold text-info" id="itmDistributed">0</div>
                    </div>
                </div>
                <div class="col">
                    <div class="bg-white rounded-2 py-1">
                        <div class="text-muted" style="font-size:0.72rem;">المتبقي</div>
                        <div class="fw-bold text-success" id="itmRemaining">0</div>
                    </div>
                </div>
                <div class="col">
                    <div class="bg-white rounded-2 py-1">
                        <div class="text-muted" style="font-size:0.72rem;">عملاء</div>
                        <div class="fw-bold" id="itmCustomers">0</div>
                    </div>
                </div>
            </div>
            <div class="mt-2 small text-muted" id="itmByUnit"></div>
        </div>

        <div class="row g-3">
            <div class="col-lg-6">
                <div class="table-responsive border rounded-3" style="max-height:300px; overflow-y:auto;">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light sticky-top">
                            <tr class="small text-muted">
                                <th colspan="2" class="text-center bg-light">توزيع الكميات حسب العميل</th>
                            </tr>
                            <tr class="small text-muted">
                                <th>العميل</th>
                                <th class="text-end">الكمية</th>
                            </tr>
                        </thead>
                        <tbody id="itmCustomersTbody"></tbody>
                    </table>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="table-responsive border rounded-3" style="max-height:300px; overflow-y:auto;">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light sticky-top">
                            <tr class="small text-muted">
                                <th>العميل</th>
                                <th>التوزيع</th>
                                <th class="text-end">الكمية</th>
                                <th class="text-end">البيع</th>
                                <th class="text-center">الحالة</th>
                            </tr>
                        </thead>
                        <tbody id="itmTbody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- الحالة الفارغة --}}
    <div id="itemEmpty" class="text-center text-muted py-5">
        <i class="bi bi-box fs-1 d-block mb-2 opacity-50"></i>
        <div class="small">اختر صنفاً من البحث أعلاه.</div>
    </div>
</div>
