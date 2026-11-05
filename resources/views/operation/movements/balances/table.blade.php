{{-- ═══════════════════════════════════════════════════════════
     جدول أرصدة المخزون + فلتر الفاتورة
     ═══════════════════════════════════════════════════════════ --}}

<style>
    .balance-qty { font-weight: 700; color: #198754; font-size: 1rem; }
    #balancesTable .price-input { min-width: 90px; text-align: center; font-family: 'Consolas', monospace; font-size: 0.85rem; }
    #balancesTable .price-input::-webkit-outer-spin-button,
    #balancesTable .price-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    #balancesTable .price-input { -moz-appearance: textfield; appearance: textfield; }
    #balancesTable .price-input[readonly] { background-color: #f8f9fa; cursor: not-allowed; }
    #balancesTable .btn-action { padding: 4px 8px; font-size: 0.8rem; }
    #balancesTable .row-kilo td { background-color: rgba(255, 243, 205, 0.25); }
</style>


<div class="card shadow-sm mb-3">

    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-boxes"></i> أرصدة المخزون والتسعير</h6>
        <button type="button" class="btn btn-sm btn-outline-primary" id="btnRefreshBalances"
                onclick="loadStockBalances()" title="تحديث">
            <i class="bi bi-arrow-clockwise"></i> تحديث
        </button>
    </div>

    <div class="card-body p-3">

        {{-- ✅ فلتر الفاتورة --}}
        <div class="row g-2 mb-3">
            <div class="col-md-8">
                <div class="border rounded-3 p-2 bg-light d-flex align-items-center gap-2"
                     id="invoiceFilterTrigger"
                     style="cursor:pointer; min-height:42px;"
                     onclick="openInvoicesPicker()">
                    <i class="bi bi-receipt text-primary"></i>
                    <div class="flex-grow-1">
                        <div class="text-muted small">الفاتورة المختارة</div>
                        <div class="fw-bold" id="selectedInvoiceLabel">كل الفواتير</div>
                        <div class="text-muted small" id="selectedInvoiceMeta" style="display:none;"></div>
                    </div>
                    <i class="bi bi-chevron-left text-muted small"></i>
                </div>
            </div>
            <div class="col-md-4 d-flex gap-2 align-items-end">
                <button type="button" class="btn btn-primary flex-grow-1" onclick="openInvoicesPicker()">
                    <i class="bi bi-search"></i> اختيار فاتورة
                </button>
                <button type="button" class="btn btn-outline-secondary" id="btnClearInvoiceFilter"
                        onclick="clearInvoiceFilter()" disabled>
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>

        {{-- شريط البحث --}}
        <div class="input-group mb-3">
            <span class="input-group-text bg-white"><i class="bi bi-search text-primary"></i></span>
            <input type="text" class="form-control" id="balancesSearchInput"
                   placeholder="ابحث في: الصنف / النوع / الرمز / المخزن / المورد / الوحدة..."
                   autocomplete="off" oninput="filterStockBalances()">
        </div>

        {{-- الجدول --}}
        <div class="table-responsive" style="max-height: 620px;">
            <table class="table table-hover table-bordered align-middle mb-0 app-table" id="balancesTable">
                <thead class="table-light sticky-top">
                    <tr class="text-center">
                        <th>الصنف</th>
                        <th>النوع</th>
                        <th>الرمز</th>
                        <th>المخزن</th>
                        <th>المورد</th>
                        <th>الوحدة</th>
                        <th>الكمية</th>
                        <th>التكلفة</th>
                        <th>سعر البيع</th>
                        <th>min</th>
                        <th>max</th>
                        <th style="width: 160px;">إجراءات</th>
                    </tr>
                </thead>
                <tbody id="balancesTableBody">
                    <tr><td colspan="12" class="text-center text-muted py-4">
                        <span class="spinner-border spinner-border-sm me-2"></span> جاري التحميل...
                    </td></tr>
                </tbody>
            </table>
        </div>

        <div class="text-muted small mt-2" id="balancesCountHint"></div>
    </div>
</div>

{{-- ✅ مودال اختيار الفاتورة --}}
<div class="modal fade" id="balanceInvoicePickerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-white border-0 py-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="bg-primary-subtle text-primary rounded-2 px-2 py-1">
                        <i class="bi bi-receipt"></i>
                    </span>
                    <h6 class="modal-title mb-0 fw-semibold">اختيار فاتورة شراء</h6>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">

                <div class="input-group input-group-sm mb-3">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" id="balanceInvSearch" class="form-control" placeholder="بحث برقم الفاتورة أو المورد...">
                </div>

                <div class="table-responsive border rounded-3" style="max-height:420px; overflow-y:auto;">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light sticky-top">
                            <tr class="small text-muted">
                                <th style="width:40px">#</th>
                                <th>رقم الفاتورة</th>
                                <th>التاريخ</th>
                                <th>المورد</th>
                                <th class="text-end">الإجمالي</th>
                            </tr>
                        </thead>
                        <tbody id="balanceInvoicesTbody">
                            <tr><td colspan="5" class="text-center text-muted py-3">جارٍ التحميل...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>