<div id="tab-invoices" class="d-none">

    {{-- بطاقة اختيار الفاتورة --}}
    <div class="border rounded-3 bg-light mb-3 p-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-center"
                     style="width:44px; height:44px;">
                    <i class="bi bi-receipt fs-5"></i>
                </div>
                <div>
                    <div class="text-muted small">الفاتورة المختارة</div>
                    <div class="fw-bold" id="selectedInvoiceLabel">لم يتم اختيار فاتورة</div>
                    <div class="text-muted small" id="selectedInvoiceMeta" style="display:none;"></div>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-primary" id="btnPickHistoryInvoice">
                <i class="bi bi-search"></i> اختيار فاتورة
            </button>
        </div>
    </div>

    {{-- محتوى الفاتورة --}}
    <div id="invoiceContent" class="d-none">

        {{-- الإحصائيات --}}
        <div class="row g-2 mb-3 text-center">
            <div class="col">
                <div class="bg-light rounded-2 py-2">
                    <div class="text-muted small">أصناف</div>
                    <div class="fw-bold" id="invItemsCount">0</div>
                </div>
            </div>
            <div class="col">
                <div class="bg-light rounded-2 py-2">
                    <div class="text-muted small">عملاء</div>
                    <div class="fw-bold" id="invCustomersCount">0</div>
                </div>
            </div>
            <div class="col">
                <div class="bg-light rounded-2 py-2">
                    <div class="text-muted small">الكمية الموزعة</div>
                    <div class="fw-bold text-primary" id="invQty">—</div>
                </div>
            </div>
            <div class="col">
                <div class="bg-light rounded-2 py-2">
                    <div class="text-muted small">إجمالي التكلفة</div>
                    <div class="fw-bold text-danger" id="invTotalCost">0.00</div>
                </div>
            </div>
            <div class="col">
                <div class="bg-light rounded-2 py-2">
                    <div class="text-muted small">إجمالي البيع</div>
                    <div class="fw-bold text-success" id="invTotalSale">0.00</div>
                </div>
            </div>
            <div class="col">
                <div class="bg-light rounded-2 py-2">
                    <div class="text-muted small">غير مفوتر</div>
                    <div class="fw-bold text-warning" id="invNotInvoicedCount">0</div>
                </div>
            </div>
        </div>

        {{-- عنوان القسم --}}
        <div class="mb-2">
            <h6 class="small mb-0 fw-semibold">
                <i class="bi bi-list-check text-primary"></i> توزيعات هذه الفاتورة
            </h6>
        </div>

        {{-- 🔍 شريط البحث --}}
        <div class="row g-2 mb-2 align-items-center">
            <div class="col-md-6">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text"
                           id="invoiceSearchInput"
                           class="form-control"
                           placeholder="ابحث بالاسم أو الرمز...">
                    <button type="button"
                            class="btn btn-outline-secondary"
                            id="invoiceSearchClearBtn"
                            title="مسح البحث">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>
            <div class="col-md-4">
                <select id="invoiceSearchScope" class="form-select form-select-sm">
                    <option value="all">بحث في الكل</option>
                    <option value="item">حسب الصنف</option>
                    <option value="customer">حسب العميل</option>
                </select>
            </div>
            <div class="col-md-2 text-end">
                <small class="text-muted" id="invoiceSearchCount"></small>
            </div>
        </div>

        {{-- الجدول --}}
        <div class="table-responsive border rounded-3" style="max-height:500px; overflow-y:auto;">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light sticky-top" id="invoiceTableHead"></thead>
                <tbody id="invoiceTbody"></tbody>
            </table>
        </div>
    </div>

    {{-- حالة فارغة --}}
    <div id="invoiceEmpty" class="text-center text-muted py-5">
        <i class="bi bi-receipt fs-1 d-block mb-2 opacity-50"></i>
        <div class="small">اضغط «اختيار فاتورة» لعرض توزيعاتها.</div>
    </div>
</div>