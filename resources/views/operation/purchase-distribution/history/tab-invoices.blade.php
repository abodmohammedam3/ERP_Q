<div id="tab-invoices" class="d-none">

    {{-- البحث --}}
    <div class="row g-2 mb-3 align-items-end">
        <div class="col-md-6">
            <label class="form-label small text-muted mb-1">بحث عن فاتورة (رقم أو مورد)</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white border-end-0">
                    <i class="bi bi-search text-muted"></i>
                </span>
                <input type="text" class="form-control border-start-0 ps-0"
                       id="invSearch" placeholder="اكتب رقم الفاتورة أو اسم المورد...">
            </div>
            <div id="invResults"
                 class="list-group list-group-flush border rounded-3 mt-1"
                 style="max-height:200px; overflow-y:auto; display:none;"></div>
        </div>
    </div>

    {{-- ملخص الفاتورة --}}
    <div id="invSummaryBox" class="d-none">
        <div class="bg-info-subtle rounded-3 p-3 mb-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="text-muted small">الفاتورة</div>
                    <div class="fw-bold fs-6" id="invNumber">—</div>
                    <div class="text-muted small" id="invSupplier">—</div>
                    <div class="text-muted small" id="invDate">—</div>
                </div>
                <div class="text-end">
                    <div class="text-muted small">إجمالي الفاتورة</div>
                    <div class="fw-bold text-primary" id="invTotal">0</div>
                </div>
            </div>
            <div class="row g-2 mt-2 text-center">
                <div class="col">
                    <div class="bg-white rounded-2 py-1">
                        <div class="text-muted" style="font-size:0.72rem;">أصناف</div>
                        <div class="fw-bold" id="invItemsCount">0</div>
                    </div>
                </div>
                <div class="col">
                    <div class="bg-white rounded-2 py-1">
                        <div class="text-muted" style="font-size:0.72rem;">عملاء</div>
                        <div class="fw-bold" id="invCustomersCount">0</div>
                    </div>
                </div>
                <div class="col">
                    <div class="bg-white rounded-2 py-1">
                        <div class="text-muted" style="font-size:0.72rem;">الكمية الموزعة</div>
                        <div class="fw-bold text-primary" id="invQty">—</div>
                    </div>
                </div>
                <div class="col">
                    <div class="bg-white rounded-2 py-1">
                        <div class="text-muted" style="font-size:0.72rem;">إجمالي التكلفة</div>
                        <div class="fw-bold text-danger" id="invTotalCost">0.00</div>
                    </div>
                </div>
                <div class="col">
                    <div class="bg-white rounded-2 py-1">
                        <div class="text-muted" style="font-size:0.72rem;">إجمالي البيع</div>
                        <div class="fw-bold text-success" id="invTotalSale">0.00</div>
                    </div>
                </div>
                <div class="col">
                    <div class="bg-white rounded-2 py-1">
                        <div class="text-muted" style="font-size:0.72rem;">غير مفوتر</div>
                        <div class="fw-bold text-warning" id="invNotInvoicedCount">0</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive border rounded-3" style="max-height:420px; overflow-y:auto;">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light sticky-top">
                    <tr class="small text-muted">
                        <th>الصنف</th>
                        <th>التوزيع</th>
                        <th>التاريخ</th>
                        <th>العميل</th>
                        <th class="text-end">الكمية</th>
                        <th class="text-center">الوحدة</th>
                        <th class="text-end">تكلفة</th>
                        <th class="text-end">بيع</th>
                        <th class="text-center">الحالة</th>
                    </tr>
                </thead>
                <tbody id="invTbody"></tbody>
            </table>
        </div>
    </div>

    {{-- الحالة الفارغة --}}
    <div id="invEmpty" class="text-center text-muted py-5">
        <i class="bi bi-receipt fs-1 d-block mb-2 opacity-50"></i>
        <div class="small">اختر فاتورة من البحث أعلاه.</div>
    </div>
</div>