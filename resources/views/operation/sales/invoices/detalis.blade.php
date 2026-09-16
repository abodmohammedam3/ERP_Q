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
{{-- قالب صف التفاصيل --}}
{{-- ===================================================== --}}
<template id="salesRowTemplate">
    <tr class="sales-detail-row">

        <td class="row-num"></td>

        <td>
            <input type="hidden" class="row-item-id">
            <input
                type="text"
                class="form-control form-control-sm row-item"
                placeholder="الصنف"
                disabled
                data-lookup="item"
                data-lookup-id-field="itemID"
                data-lookup-display-field="itemName2"
                autocomplete="off"
            >
        </td>

        <td>
            <input type="hidden" class="row-type-id">
            <input
                type="text"
                class="form-control form-control-sm row-type"
                placeholder="النوع"
                disabled
                data-lookup="type"
                data-lookup-id-field="id"
                data-lookup-display-field="name"
                autocomplete="off"
            >
        </td>

        <td>
            <input
                type="text"
                class="form-control form-control-sm row-code"
                placeholder="الرمز"
                disabled
            >
        </td>

        <td>
            <select class="form-select form-select-sm row-unit" disabled></select>
        </td>

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

        <td>
            <input type="hidden" class="row-warehouse-id">
            <input
                type="text"
                class="form-control form-control-sm row-warehouse"
                placeholder="المخزن"
                disabled
                data-lookup="warehouse"
                data-lookup-id-field="StockID"
                data-lookup-display-field="StockName"
                autocomplete="off"
            >
        </td>

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

        <td>
            <input
                type="number"
                class="form-control form-control-sm row-total"
                value="0.00"
                readonly
                tabindex="-1"
            >
        </td>

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