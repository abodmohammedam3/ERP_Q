<!-- ========================= -->
<!-- تفاصيل مرتجع الشراء -->
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
                    <th style="width:16%;">الصنف</th>
                    <th style="width:11%;">النوع</th>
                    <th style="width:10%;">الرمز</th>
                    <th style="width:10%;">الوحدة</th>
                    <th style="width:11%;">سعر الشراء</th>
                    <th style="width:11%;">الكمية المتاحة</th>
                    <th style="width:11%;">كمية المرتجع</th>
                    <th style="width:8%;">الخصم</th>
                    <th style="width:12%;">الإجمالي</th>
                    <th style="width:6%;">إجراء</th>
                </tr>
            </thead>

            <tbody id="purchaseReturnDetails">
                <tr>
                    <td colspan="11" class="text-center text-muted py-4">
                        اختر الفاتورة الأصلية لإدراج الأصناف المتاحة للإرجاع
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
                                    <strong class="text-danger" id="totalPurchaseReturnDiscountDisplay">0.00</strong>
                                </div>

                                <div class="vr"></div>

                                <div class="d-flex align-items-center gap-2">
                                    <span class="text-danger small fw-bold">إجمالي المرتجع</span>
                                    <strong class="text-danger fs-5" id="purchaseReturnTotalDisplay">0.00</strong>
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
                <label for="PurchaseReturnStatement" class="form-label">البيان / ملاحظات المرتجع</label>
                <textarea
                    class="form-control"
                    id="PurchaseReturnStatement"
                    name="PurchaseReturnStatement"
                    rows="2"
                    disabled
                ></textarea>
            </div>

            <div class="col-md-6">
                <label for="PurchaseReturnReference" class="form-label">المرجع</label>
                <input
                    type="text"
                    class="form-control"
                    id="PurchaseReturnReference"
                    name="PurchaseReturnReference"
                    placeholder="رقم مرجعي إن وجد"
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
        id="btnCancelPurchaseReturn"
        onclick="cancelPurchaseReturn()"
    >
        <i class="bi bi-x-lg"></i>
        إلغاء
    </button>

    <button
        type="button"
        class="btn btn-success"
        id="btnSavePurchaseReturn"
        onclick="savePurchaseReturn()"
        disabled
    >
        <i class="bi bi-save"></i>
        حفظ المرتجع
    </button>

    <button
        type="button"
        class="btn btn-primary"
        id="btnSaveAndNewPurchaseReturn"
        onclick="saveAndNewPurchaseReturn()"
        disabled
    >
        <i class="bi bi-save2"></i>
        حفظ وإضافة مرتجع
    </button>

    <!-- ✅ ترحيل المرتجع (للمسودات) -->
    <button
        type="button"
        class="btn btn-success d-none"
        id="btnPostPurchaseReturn"
        onclick="postCurrentPurchaseReturn()"
        disabled
    >
        <i class="bi bi-check2-circle"></i>
        ترحيل
    </button>

    <!-- ✅ إلغاء الترحيل (للمرحّلات) -->
    <button
        type="button"
        class="btn btn-warning d-none"
        id="btnUnpostPurchaseReturn"
        onclick="unpostCurrentPurchaseReturn()"
        disabled
    >
        <i class="bi bi-arrow-counterclockwise"></i>
        إلغاء الترحيل
    </button>

    <button
        type="button"
        class="btn btn-warning"
        id="btnEditPurchaseReturn"
        onclick="editPurchaseReturn()"
        disabled
    >
        <i class="bi bi-pencil-square"></i>
        تعديل
    </button>

    <button
        type="button"
        class="btn btn-dark"
        id="btnPrintPurchaseReturn"
        onclick="printPurchaseReturn()"
        disabled
    >
        <i class="bi bi-printer"></i>
        طباعة
    </button>

</div>





<template id="purchaseReturnRowTemplate">
    <tr class="return-detail-row">

        <td class="row-num"></td>

        <input type="hidden" class="row-detail-id">

        
        <td>
            <input type="hidden" class="row-item-id">
            <input
                type="text"
                class="form-control form-control-sm row-item"
                readonly
            >
        </td>

        
        <td>
            <input type="hidden" class="row-type-id">
            <input
                type="text"
                class="form-control form-control-sm row-type"
                readonly
            >
        </td>

        
        <td>
            <input
                type="text"
                class="form-control form-control-sm row-code"
                readonly
            >
        </td>

        
        <td>
            <input type="hidden" class="row-unit-id">
            <input
                type="text"
                class="form-control form-control-sm row-unit"
                readonly
            >
        </td>

        
        <td>
            <input
                type="number"
                class="form-control form-control-sm row-price"
                readonly
                tabindex="-1"
            >
        </td>

        
        <td>
            <input
                type="number"
                class="form-control form-control-sm row-available bg-light text-primary fw-bold"
                readonly
                tabindex="-1"
            >
        </td>

        
        <td>
            <input
                type="number"
                class="form-control form-control-sm row-measure border-primary fw-bold"
                value="0"
                min="0"
                step="0.001"
                disabled
                oninput="calculatePurchaseReturnRow(this)"
            >
        </td>

        
        <td>
            <input
                type="number"
                class="form-control form-control-sm row-discount bg-light"
                value="0.00"
                readonly
                tabindex="-1"
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
                onclick="removePurchaseReturnRow(this)"
                disabled
                tabindex="-1"
            >
                <i class="bi bi-trash3"></i>
            </button>
        </td>

        <input type="hidden" class="row-unit-cost" value="0">
        <input type="hidden" class="row-original-qty" value="0">
        <input type="hidden" class="row-original-discount" value="0">

    </tr>
</template><?php /**PATH C:\Laravel\qaatSystem\resources\views/operation/purchases/returns/details.blade.php ENDPATH**/ ?>