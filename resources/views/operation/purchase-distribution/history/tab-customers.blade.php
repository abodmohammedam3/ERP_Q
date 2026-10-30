<div id="tab-customers" class="d-none">

    {{-- البحث --}}
    <div class="row g-2 mb-3 align-items-end">
        <div class="col-md-6">
            <label class="form-label small text-muted mb-1">بحث عن عميل (اسم أو جوال)</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white border-end-0">
                    <i class="bi bi-search text-muted"></i>
                </span>
                <input type="text" class="form-control border-start-0 ps-0"
                       id="custSearch" placeholder="اكتب حرفين على الأقل...">
            </div>
            <div id="custResults"
                 class="list-group list-group-flush border rounded-3 mt-1"
                 style="max-height:200px; overflow-y:auto; display:none;"></div>
        </div>
        <div class="col-md-3">
            <label class="form-label small text-muted mb-1">الفوترة</label>
            <select class="form-select form-select-sm" id="custInvoicedFilter">
                <option value="all">الكل</option>
                <option value="not_invoiced">غير مفوتر فقط</option>
                <option value="invoiced">مفوتر فقط</option>
            </select>
        </div>
    </div>

    {{-- ملخص العميل --}}
    <div id="custSummaryBox" class="d-none">
        <div class="bg-primary-subtle rounded-3 p-3 mb-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small">العميل</div>
                    <div class="fw-bold fs-6" id="custName">—</div>
                    <div class="text-muted small" id="custPhone">—</div>
                </div>
                <button type="button" class="btn btn-sm btn-success" id="btnIssueForCustomer" disabled>
                    <i class="bi bi-receipt"></i> إصدار فاتورة
                </button>
            </div>
            <div class="row g-2 mt-2 text-center">
                <div class="col">
                    <div class="bg-white rounded-2 py-1">
                        <div class="text-muted" style="font-size:0.72rem;">صفوف</div>
                        <div class="fw-bold" id="custRowsCount">0</div>
                    </div>
                </div>
                <div class="col">
                    <div class="bg-white rounded-2 py-1">
                        <div class="text-muted" style="font-size:0.72rem;">غير مفوتر</div>
                        <div class="fw-bold text-warning" id="custNotInvoicedCount">0</div>
                    </div>
                </div>
                <div class="col">
                    <div class="bg-white rounded-2 py-1">
                        <div class="text-muted" style="font-size:0.72rem;">إجمالي التكلفة</div>
                        <div class="fw-bold text-danger" id="custTotalCost">0.00</div>
                    </div>
                </div>
                <div class="col">
                    <div class="bg-white rounded-2 py-1">
                        <div class="text-muted" style="font-size:0.72rem;">إجمالي البيع</div>
                        <div class="fw-bold text-success" id="custTotalSale">0.00</div>
                    </div>
                </div>
                <div class="col">
                    <div class="bg-white rounded-2 py-1">
                        <div class="text-muted" style="font-size:0.72rem;">كمية غير مفوترة</div>
                        <div class="fw-bold text-primary" id="custNotInvoicedQty">—</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive border rounded-3" style="max-height:420px; overflow-y:auto;">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light sticky-top">
                    <tr class="small text-muted">
                        <th>الصنف</th>
                        <th>الفاتورة</th>
                        <th>التوزيع</th>
                        <th class="text-end">الكمية</th>
                        <th class="text-center">الوحدة</th>
                        <th class="text-end">تكلفة</th>
                        <th class="text-end">بيع</th>
                        <th class="text-center">الحالة</th>
                    </tr>
                </thead>
                <tbody id="custTbody"></tbody>
            </table>
        </div>
    </div>

    {{-- الحالة الفارغة --}}
    <div id="custEmpty" class="text-center text-muted py-5">
        <i class="bi bi-people fs-1 d-block mb-2 opacity-50"></i>
        <div class="small">اختر عميلًا من البحث أعلاه.</div>
    </div>
</div>