<!-- ========================= -->
<!-- تفاصيل مرتجع البيع -->
<!-- ========================= -->

<div class="card mb-3">

    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>
            <i class="bi bi-box-seam me-1"></i>
            تفاصيل أصناف المرتجع
        </strong>
    </div>

    <div class="card-body p-0">

        <table class="table table-bordered table-hover align-middle text-center mb-0">

            <thead class="table-light">
                <tr>
                    <th style="width:4%;">الرقم</th>
                    <th style="width:14%;">الصنف</th>
                    <th style="width:9%;">النوع</th>
                    <th style="width:8%;">الرمز</th>
                    <th style="width:8%;">الوحدة</th>
                    <th style="width:9%;">المخزن</th>
                    <th style="width:9%;">سعر الوحدة</th>
                    <th style="width:9%;">الكمية المتاحة</th>
                    <th style="width:9%;">كمية المرتجع</th>
                    <th style="width:8%;">الخصم</th>
                    <th style="width:11%;">الإجمالي</th>
                    <th style="width:6%;">إجراء</th>
                </tr>
            </thead>

            <tbody id="salesReturnDetails">
                <tr>
                    <td colspan="12" class="text-center text-muted py-4">
                        اختر الفاتورة الأصلية لإدراج الأصناف المتاحة للإرجاع
                    </td>
                </tr>
            </tbody>

            <tfoot>
                <tr>
                    <td colspan="12" class="p-3 bg-light">
                        <div class="d-flex justify-content-end">
                            <div class="d-flex align-items-center gap-3 border rounded-3 bg-white p-2 px-3">

                                <div class="d-flex align-items-center gap-2">
                                    <span class="text-danger small fw-bold">إجمالي الخصم</span>
                                    <strong class="text-danger" id="totalSalesReturnDiscountDisplay">0.00</strong>
                                </div>

                                <div class="vr"></div>

                                <div class="d-flex align-items-center gap-2">
                                    <span class="text-success small fw-bold">إجمالي المرتجع</span>
                                    <strong class="text-success fs-5" id="salesReturnTotalDisplay">0.00</strong>
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
            <i class="bi bi-card-text me-1"></i>
            بيانات إضافية
        </strong>
    </div>

    <div class="card-body">

        <div class="row g-3">

            <div class="col-md-6">
                <label for="SalesReturnStatement" class="form-label">البيان / ملاحظات المرتجع</label>
                <textarea
                    class="form-control"
                    id="SalesReturnStatement"
                    name="SalesReturnStatement"
                    rows="2"
                    readonly
                    tabindex="-1"
                ></textarea>
            </div>

            <div class="col-md-6">
                <label for="SalesReturnReference" class="form-label">المرجع</label>
                <input
                    type="text"
                    class="form-control"
                    id="SalesReturnReference"
                    name="SalesReturnReference"
                    placeholder="رقم مرجعي إن وجد"
                    readonly
                    tabindex="-1"
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
        id="btnCancelSalesReturn"
        onclick="cancelSalesReturn()"
    >
        <i class="bi bi-x-lg"></i>
        إلغاء
    </button>

    <button
        type="button"
        class="btn btn-success"
        id="btnSaveSalesReturn"
        onclick="saveSalesReturn()"
        disabled
    >
        <i class="bi bi-save"></i>
        حفظ المرتجع
    </button>

    <button
        type="button"
        class="btn btn-primary"
        id="btnSaveAndNewSalesReturn"
        onclick="saveAndNewSalesReturn()"
        disabled
    >
        <i class="bi bi-save2"></i>
        حفظ وإضافة مرتجع
    </button>

    <button
        type="button"
        class="btn btn-success d-none"
        id="btnPostSalesReturn"
        onclick="postCurrentSalesReturn()"
        disabled
    >
        <i class="bi bi-check2-circle"></i>
        ترحيل
    </button>

    <button
        type="button"
        class="btn btn-warning d-none"
        id="btnUnpostSalesReturn"
        onclick="unpostCurrentSalesReturn()"
        disabled
    >
        <i class="bi bi-arrow-counterclockwise"></i>
        إلغاء الترحيل
    </button>

    <button
        type="button"
        class="btn btn-warning"
        id="btnEditSalesReturn"
        onclick="editSalesReturn()"
        disabled
    >
        <i class="bi bi-pencil-square"></i>
        تعديل
    </button>

    <button
        type="button"
        class="btn btn-dark"
        id="btnPrintSalesReturn"
        onclick="printSalesReturn()"
        disabled
    >
        <i class="bi bi-printer"></i>
        طباعة
    </button>

</div>





<template id="salesReturnRowTemplate">
    <tr class="return-detail-row">

        <td class="row-num"></td>

        <input type="hidden" class="row-detail-id">

        <td>
            <input type="hidden" class="row-item-id">
            <input type="text" class="form-control form-control-sm row-item" readonly tabindex="-1">
        </td>

        <td>
            <input type="hidden" class="row-type-id">
            <input type="text" class="form-control form-control-sm row-type" readonly tabindex="-1">
        </td>

        <td>
            <input type="text" class="form-control form-control-sm row-code" readonly tabindex="-1">
        </td>

        <td>
            <input type="hidden" class="row-unit-id">
            <input type="text" class="form-control form-control-sm row-unit" readonly tabindex="-1">
        </td>

        <td>
            <input type="hidden" class="row-warehouse-id">
            <input type="text" class="form-control form-control-sm row-warehouse" readonly tabindex="-1">
        </td>

        <td>
            <input type="number" class="form-control form-control-sm row-price" readonly tabindex="-1">
        </td>

        <td>
            <input type="number" class="form-control form-control-sm row-available bg-light text-primary fw-bold" readonly tabindex="-1">
        </td>

        <td>
            <input
                type="number"
                class="form-control form-control-sm row-measure border-primary fw-bold"
                value="0" min="0" step="0.001"
                disabled
                oninput="calculateSalesReturnRow(this)"
            >
        </td>

        <td>
            <input
                type="number"
                class="form-control form-control-sm row-discount border-success"
                value="0" min="0" step="0.01"
                disabled
                oninput="calculateSalesReturnRow(this)"
            >
        </td>

        <td>
            <input type="number" class="form-control form-control-sm row-total" value="0.00" readonly tabindex="-1">
        </td>

        <td>
            <button
                type="button"
                class="btn btn-sm btn-outline-danger row-remove-btn"
                onclick="removeSalesReturnRow(this)"
                disabled
                tabindex="-1"
            >
                <i class="bi bi-trash3"></i>
            </button>
        </td>

        <input type="hidden" class="row-cost-price" value="0">
        <input type="hidden" class="row-original-qty" value="0">
        <input type="hidden" class="row-original-discount" value="0">

    </tr>
</template><?php /**PATH C:\Laravel\qaatSystem\resources\views/operation/sales/returns/details.blade.php ENDPATH**/ ?>