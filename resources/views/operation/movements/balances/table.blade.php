{{-- ═══════════════════════════════════════════════════════════
     جدول أرصدة المخزون
     ═══════════════════════════════════════════════════════════ --}}

<style>
    .balance-qty {
        font-weight: 700;
        color: #198754;
        font-size: 1rem;
        direction: ltr;
        font-family: 'Consolas', monospace;
    }

    .balance-cost {
        font-family: 'Consolas', monospace;
        direction: ltr;
    }

    #balancesTable .price-input {
        min-width: 90px;
        text-align: center;
        font-family: 'Consolas', monospace;
        font-size: 0.85rem;
    }

    #balancesTable .price-input::-webkit-outer-spin-button,
    #balancesTable .price-input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    #balancesTable .price-input {
        -moz-appearance: textfield;
        appearance: textfield;
    }

    #balancesTable .btn-action {
        padding: 4px 8px;
        font-size: 0.8rem;
    }

    #balancesTable .row-kilo td {
        background-color: rgba(255, 243, 205, 0.25);
    }
</style>


<div class="card shadow-sm mb-3">

    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">
            <i class="bi bi-boxes"></i>
            أرصدة المخزون والتسعير
        </h6>

        <button
            type="button"
            class="btn btn-sm btn-outline-primary"
            id="btnRefreshBalances"
            onclick="loadStockBalances()"
            title="تحديث"
        >
            <i class="bi bi-arrow-clockwise"></i>
            تحديث
        </button>
    </div>

    <div class="card-body p-3">

        {{-- شريط البحث --}}
        <div class="input-group mb-3">
            <span class="input-group-text bg-white">
                <i class="bi bi-search text-primary"></i>
            </span>
            <input
                type="text"
                class="form-control"
                id="balancesSearchInput"
                placeholder="ابحث في: الصنف / النوع / الرمز / المخزن / الوحدة..."
                autocomplete="off"
                oninput="filterStockBalances()"
            >
        </div>

        {{-- الجدول --}}
        <div class="table-responsive" style="max-height: 620px;">
            <table class="table table-hover table-bordered align-middle mb-0"
                   id="balancesTable"
                   style="font-size: 0.85rem;">
                <thead class="table-light sticky-top">
                    <tr class="text-center">
                        <th>الصنف</th>
                        <th>النوع</th>
                        <th>الرمز</th>
                        <th>المخزن</th>
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
                    <tr>
                        <td colspan="11" class="text-center text-muted py-4">
                            <span class="spinner-border spinner-border-sm me-2"></span>
                            جاري التحميل...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- عدد النتائج --}}
        <div class="text-muted small mt-2" id="balancesCountHint"></div>

    </div>

</div>