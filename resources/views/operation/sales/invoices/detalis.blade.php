<!-- ========================= -->
<!-- تفاصيل الفاتورة -->
<!-- ========================= -->

<div class="card mb-3">

    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>
            <i class="bi bi-box-seam"></i>
            تفاصيل الأصناف
        </strong>

        <button
            type="button"
            class="btn btn-sm btn-success"
            id="btnAddSalesRow"
            onclick="addSalesRow()"
            disabled
        >
            <i class="bi bi-plus-lg"></i>
            إضافة صنف
        </button>
    </div>

    <div class="card-body p-0">

        <table class="table table-bordered table-hover align-middle text-center mb-0">

            <thead class="table-light">
                <tr>
                    <th style="width:4%;">الرقم</th>
                    <th style="width:13%;">الصنف</th>
                    <th style="width:9%;">النوع</th>
                    <th style="width:8%;">الرمز</th>
                    <th style="width:9%;">الوحدة</th>
                    <th style="width:9%;">سعر الوحدة</th>
                    <th style="width:11%;">المخزن</th>
                    <th style="width:8%;">العدد</th>
                    <th style="width:8%;">الخصم</th>
                    <th style="width:10%;">الإجمالي</th>
                    <th style="width:6%;">إجراء</th>
                </tr>
            </thead>

            <tbody id="salesInvoiceDetails">
                <tr>
                    <td colspan="11" class="text-center text-muted py-4">
                        لا توجد أصناف مضافة إلى الفاتورة
                    </td>
                </tr>
            </tbody>

            <tfoot>
                <tr>
                    <td colspan="11" class="p-3 bg-light">
                        <div class="d-flex justify-content-end">
                            <div class="d-flex align-items-center gap-3 border rounded-3 bg-white p-2 px-3">

                                <div class="d-flex align-items-center gap-2">
                                    <span class="text-danger small fw-bold">إجمالي الخصم</span>
                                    <strong class="text-danger" id="totalSalesDiscountDisplay">0.00</strong>
                                </div>

                                <div class="vr"></div>

                                <div class="d-flex align-items-center gap-2">
                                    <span class="text-success small fw-bold">إجمالي الفاتورة</span>
                                    <strong class="text-success fs-5" id="salesInvoiceTotalDisplay">0.00</strong>
                                </div>

                            </div>
                        </div>
                    </td>
                </tr>
            </tfoot>

        </table>

    </div>

</div>


<!-- ===================================================== -->
<!-- البيانات الإضافية -->
<!-- ===================================================== -->

<div class="card mb-3">

    <div class="card-header">
        <strong>
            <i class="bi bi-card-text"></i>
            بيانات إضافية
        </strong>
    </div>

    <div class="card-body">

        <div class="row g-3">

            <div class="col-md-6">
                <label for="SalesStatement" class="form-label">البيان</label>
                <textarea
                    class="form-control"
                    id="SalesStatement"
                    name="SalesStatement"
                    rows="2"
                    disabled
                ></textarea>
            </div>

            <div class="col-md-6">
                <label for="SalesReference" class="form-label">المرجع</label>
                <input
                    type="text"
                    class="form-control"
                    id="SalesReference"
                    name="SalesReference"
                    placeholder="المرجع"
                    disabled
                >
            </div>

        </div>

    </div>

</div>


<!-- ========================= -->
<!-- أزرار العملية -->
<!-- ========================= -->

<div class="d-flex justify-content-end gap-2 mt-3 flex-wrap">

    <button
        type="button"
        class="btn btn-secondary d-none"
        id="btnCancelSalesInvoice"
        onclick="cancelSalesInvoice()"
    >
        <i class="bi bi-x-lg"></i>
        إلغاء
    </button>

    <button
        type="button"
        class="btn btn-success"
        id="btnSaveSalesInvoice"
        onclick="saveSalesInvoice()"
        disabled
    >
        <i class="bi bi-save"></i>
        حفظ
    </button>

    <button
        type="button"
        class="btn btn-primary"
        id="btnSaveAndNewSalesInvoice"
        onclick="saveAndNewSalesInvoice()"
        disabled
    >
        <i class="bi bi-save2"></i>
        حفظ وإضافة فاتورة
    </button>

    <button
        type="button"
        class="btn btn-warning"
        id="btnEditSalesInvoice"
        onclick="editSalesInvoice()"
        disabled
    >
        <i class="bi bi-pencil-square"></i>
        تعديل
    </button>

    <button
        type="button"
        class="btn btn-dark"
        id="btnPrintSalesInvoice"
        onclick="printSalesInvoice()"
        disabled
    >
        <i class="bi bi-printer"></i>
        طباعة
    </button>

</div>


{{-- ===================================================== --}}
{{-- ✅ قالب صف التفاصيل (معدَّل — يفتح نافذة الأرصدة) --}}
{{-- ===================================================== --}}
<template id="salesRowTemplate">
    <tr class="sales-detail-row">

        <td class="row-num"></td>

        {{-- الصنف --}}
        <td>
            <input type="hidden" class="row-item-id">
            <input
                type="text"
                class="form-control form-control-sm row-item"
                placeholder="الصنف"
                readonly
                onclick="openStockBalancesModal(this)"
                autocomplete="off"
                style="cursor:pointer;"
            >
        </td>

        {{-- النوع --}}
        <td>
            <input type="hidden" class="row-type-id">
            <input
                type="text"
                class="form-control form-control-sm row-type"
                placeholder="النوع"
                readonly
                onclick="openStockBalancesModal(this)"
                autocomplete="off"
                style="cursor:pointer;"
            >
        </td>

        {{-- الرمز --}}
        <td>
            <input
                type="text"
                class="form-control form-control-sm row-code"
                placeholder="الرمز"
                readonly
            >
        </td>

        {{-- الوحدة (input + hidden ID) --}}
        <td>
            <input type="hidden" class="row-unit-id">
            <input
                type="text"
                class="form-control form-control-sm row-unit"
                placeholder="الوحدة"
                readonly
                onclick="openStockBalancesModal(this)"
                autocomplete="off"
                style="cursor:pointer;"
            >
        </td>

        {{-- سعر الوحدة --}}
        <td>
            <input
                type="number"
                class="form-control form-control-sm row-price"
                value="0"
                min="0"
                step="0.01"
                disabled
                oninput="calculateSalesRow(this)"
            >
        </td>

        {{-- المخزن --}}
        <td>
            <input type="hidden" class="row-warehouse-id">
            <input
                type="text"
                class="form-control form-control-sm row-warehouse"
                placeholder="المخزن"
                readonly
                onclick="openStockBalancesModal(this)"
                autocomplete="off"
                style="cursor:pointer;"
            >
        </td>

        {{-- العدد --}}
        <td>
            <input
                type="number"
                class="form-control form-control-sm row-measure"
                value="0"
                min="0"
                step="0.001"
                disabled
                oninput="calculateSalesRow(this)"
            >
        </td>

        {{-- الخصم --}}
        <td>
            <input
                type="number"
                class="form-control form-control-sm row-discount"
                value="0"
                min="0"
                step="0.01"
                disabled
                oninput="calculateSalesRow(this)"
            >
        </td>

        {{-- الإجمالي --}}
        <td>
            <input
                type="number"
                class="form-control form-control-sm row-total"
                value="0.00"
                readonly
                tabindex="-1"
            >
        </td>

        {{-- إجراء --}}
        <td>
            <button
                type="button"
                class="btn btn-sm btn-outline-danger"
                onclick="removeSalesRow(this)"
                disabled
                tabindex="-1"
            >
                <i class="bi bi-trash3"></i>
            </button>
        </td>

        <input type="hidden" class="row-cost-price" value="0">

    </tr>
</template>


{{-- ===================================================== --}}
{{-- ✅ نافذة الأرصدة والتسعير --}}
{{-- ===================================================== --}}
<div class="modal fade" id="stockBalancesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-boxes text-primary me-2"></i>
                    الأرصدة والتسعير
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>

            <div class="modal-body p-3">

                <div class="input-group mb-3">
                    <span class="input-group-text bg-white">
                        <i class="bi bi-search"></i>
                    </span>
                    <input
                        type="text"
                        class="form-control"
                        id="stockBalancesSearch"
                        placeholder="ابحث في: الصنف / النوع / الرمز / المخزن..."
                        autocomplete="off"
                        oninput="filterStockBalances()"
                    >
                </div>

                <div class="table-responsive" style="max-height: 450px;">
                    <table class="table table-hover table-bordered align-middle mb-0" style="font-size: 0.85rem;">
                        <thead class="table-light sticky-top">
                            <tr class="text-center">
                                <th>الصنف</th>
                                <th>النوع</th>
                                <th>الرمز</th>
                                <th>المخزن</th>
                                <th>الوحدة</th>
                                <th>الرصيد</th>
                                <th>التكلفة</th>
                                <th>سعر البيع</th>
                                <th>min</th>
                                <th>max</th>
                                <th style="width:140px;">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody id="stockBalancesBody">
                            <tr>
                                <td colspan="11" class="text-center text-muted py-4">
                                    <span class="spinner-border spinner-border-sm me-2"></span>
                                    جاري التحميل...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>

        </div>
    </div>
</div>


{{-- ===================================================== --}}
{{-- ✅ نافذة الفرز (تُفتح من نافذة الأرصدة) --}}
{{-- ===================================================== --}}
<div class="modal fade" id="sortingFromBalanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-scissors text-primary me-2"></i>
                    فرز الدفعة
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>

            <div class="modal-body">

                <div class="alert alert-info py-2 mb-3">
                    <div class="d-flex justify-content-between">
                        <span><strong>الصنف:</strong> <span id="sortingItemName">—</span></span>
                        <span><strong>النوع:</strong> <span id="sortingTypeName">—</span></span>
                    </div>
                    <div class="d-flex justify-content-between mt-1">
                        <span><strong>المخزن:</strong> <span id="sortingWarehouseName">—</span></span>
                        <span><strong>الرصيد:</strong> <span id="sortingAvailable">0</span> كجم</span>
                    </div>
                    <div class="d-flex justify-content-between mt-1">
                        <span><strong>تكلفة الكيلو:</strong> <span id="sortingUnitCost">0</span></span>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">الكمية المفرزة (كجم) <span class="text-danger">*</span></label>
                        <input
                            type="number"
                            class="form-control"
                            id="sortingInputQty"
                            step="0.001"
                            min="0.001"
                            oninput="calculateSortingFromBalance()"
                        >
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">عدد الحبات الناتجة <span class="text-danger">*</span></label>
                        <input
                            type="number"
                            class="form-control"
                            id="sortingOutputQty"
                            step="1"
                            min="1"
                            oninput="calculateSortingFromBalance()"
                        >
                    </div>
                </div>

                <div class="alert alert-success mt-3 py-2">
                    <div class="d-flex justify-content-between">
                        <span>تكلفة الحبة:</span>
                        <strong id="sortingResultUnitCost">0.00</strong>
                    </div>
                    <div class="d-flex justify-content-between mt-1">
                        <span>القيمة الإجمالية:</span>
                        <strong id="sortingResultTotal">0.00</strong>
                    </div>
                </div>

                <div class="row g-3 mt-2">
                    <div class="col-md-4">
                        <label class="form-label">سعر البيع للحبة</label>
                        <input type="number" class="form-control" id="sortingSalePrice" step="0.01" min="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">الحد الأدنى</label>
                        <input type="number" class="form-control" id="sortingMinPrice" step="0.01" min="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">الحد الأعلى</label>
                        <input type="number" class="form-control" id="sortingMaxPrice" step="0.01" min="0">
                    </div>
                </div>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-primary" id="btnSaveSorting" onclick="saveSortingFromBalance()">
                    <i class="bi bi-check-lg"></i>
                    حفظ الفرز
                </button>
            </div>

        </div>
    </div>
</div>