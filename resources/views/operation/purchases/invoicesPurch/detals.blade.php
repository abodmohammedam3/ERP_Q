<!-- ========================= -->
<!-- تفاصيل الفاتورة -->
<!-- ========================= -->

<div class="card mb-3">

    @include('operation.purchases.invoicesPurch.haed')

    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>
            <i class="bi bi-box-seam"></i>
            تفاصيل الأصناف
        </strong>

        <button
            type="button"
            class="btn btn-sm btn-success"
            id="btnAddInvoiceRow"
            onclick="addInvoiceRow()"
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
                    <th style="width: 5%;">الرقم</th>
                    <th style="width: 16%;">الصنف</th>
                    <th style="width: 10%;">النوع</th>
                    <th style="width: 9%;">الرمز</th>
                    <th style="width: 11%;">الوحدة</th>
                    <th style="width: 11%;">عدد</th>
                    <th style="width: 10%;">سعر الوحدة</th>
                    <th style="width: 9%;">الخصم</th>
                    <th style="width: 12%;">الإجمالي</th>
                    <th style="width: 7%;">إجراء</th>
                </tr>
            </thead>

            <tbody id="purchaseInvoiceDetails">
                <tr>
                    <td colspan="10" class="text-center text-muted py-4">
                        لا توجد أصناف مضافة إلى الفاتورة
                    </td>
                </tr>
            </tbody>

            <!-- الإجماليات -->
            <tfoot>
                <tr>
                    <td colspan="10" class="p-3 bg-light">
                        <div class="row g-3 align-items-center">

                            {{-- المبلغ كتابة --}}
                            <div class="col-md-7">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white fw-bold text-secondary text-nowrap">
                                        <i class="bi bi-fonts me-1"></i>
                                        المبلغ كتابة
                                    </span>
                                    <input
                                        type="text"
                                        class="form-control amount-words"
                                        id="AmountWords"
                                        name="amountWords"
                                        readonly
                                        placeholder="سيظهر المبلغ كتابة هنا تلقائيًا"
                                    >
                                </div>
                            </div>

                            {{-- الإجماليات --}}
                            <div class="col-md-5">
                                <div class="d-flex align-items-center justify-content-end gap-3 border rounded-3 bg-white p-2 px-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="text-danger small fw-bold">إجمالي الخصم</span>
                                        <strong class="text-danger" id="totalDiscountDisplay">0.00</strong>
                                    </div>
                                    <div class="vr"></div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="text-success small fw-bold">إجمالي الفاتورة</span>
                                        <strong class="text-success fs-5" id="invoiceTotalDisplay">0.00</strong>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </td>
                </tr>
            </tfoot>

        </table>

    </div>

</div>


<!-- ========================= -->
<!-- البيانات الإضافية -->
<!-- ========================= -->

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
                <label for="PuInStatement2" class="form-label">البيان</label>
                <textarea
                    class="form-control"
                    id="PuInStatement2"
                    name="PuInStatement2"
                    rows="2"
                    disabled
                ></textarea>
            </div>

            <div class="col-md-6">
                <label for="invoiceReference" class="form-label">المرجع</label>
                <input
                    type="text"
                    class="form-control"
                    id="invoiceReference"
                    name="invoiceReference"
                    placeholder="المرجع"
                    disabled
                >
            </div>

        </div>
    </div>
</div>


<!-- ========================= -->
<!-- التكاليف الإضافية -->
<!-- ========================= -->

<div class="card mb-3">
    <div class="card-header">
        <strong>
            <i class="bi bi-calculator"></i>
            التكاليف الإضافية
        </strong>
    </div>
    <div class="card-body">
        <div class="row g-3">

            <div class="col-md-3">
                <label for="PuInExpenses" class="form-label">النفقات</label>
                <input
                    type="number"
                    step="0.000001"
                    min="0"
                    class="form-control"
                    id="PuInExpenses"
                    name="PuInExpenses"
                    disabled
                    oninput="calculateTotals()"
                >
            </div>

            <div class="col-md-3">
                <label for="PuInTaxCost" class="form-label">تكلفة الضريبة</label>
                <input
                    type="number"
                    step="0.000001"
                    min="0"
                    class="form-control"
                    id="PuInTaxCost"
                    name="PuInTaxCost"
                    disabled
                    oninput="calculateTotals()"
                >
            </div>

            <div class="col-md-3">
                <label for="PuInTransportation" class="form-label">تكلفة النقل</label>
                <input
                    type="number"
                    step="0.000001"
                    min="0"
                    class="form-control"
                    id="PuInTransportation"
                    name="PuInTransportation"
                    disabled
                    oninput="calculateTotals()"
                >
            </div>

            <div class="col-md-3">
                <label for="PuInOtherCost" class="form-label">تكاليف أخرى</label>
                <input
                    type="number"
                    step="0.000001"
                    min="0"
                    class="form-control"
                    id="PuInOtherCost"
                    name="PuInOtherCost"
                    disabled
                    oninput="otherCostChanged()"
                >
            </div>

            <div class="col-md-12 d-none" id="otherCostDescriptionContainer">
                <label for="otherCostDescription" class="form-label">ما هي التكلفة الأخرى؟</label>
                <input
                    type="text"
                    class="form-control"
                    id="otherCostDescription"
                    name="otherCostDescription"
                    placeholder="أدخل وصف التكلفة الأخرى"
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
        id="btnCancelInvoice"
        onclick="cancelInvoice()"
    >
        <i class="bi bi-x-lg"></i>
        إلغاء
    </button>

    <button
        type="button"
        class="btn btn-success"
        id="btnSaveInvoice"
        onclick="saveInvoice()"
        disabled
    >
        <i class="bi bi-save"></i>
        حفظ
    </button>

    <button
        type="button"
        class="btn btn-primary"
        id="btnSaveAndNew"
        onclick="saveAndNewInvoice()"
        disabled
    >
        <i class="bi bi-save2"></i>
        حفظ وإضافة فاتورة
    </button>

    <button
        type="button"
        class="btn btn-warning"
        id="btnEditInvoice"
        onclick="editInvoice()"
        disabled
    >
        <i class="bi bi-pencil-square"></i>
        تعديل
    </button>

    <button
        type="button"
        class="btn btn-dark"
        id="btnPrintInvoice"
        onclick="printInvoice()"
        disabled
    >
        <i class="bi bi-printer"></i>
        طباعة
    </button>

</div>


{{-- ===================================================== --}}
{{-- قالب صف تفاصيل الفاتورة (يُستخدم بواسطة JS) --}}
{{-- ===================================================== --}}
<template id="invoiceRowTemplate">
    <tr class="purchase-detail-row">

        <td class="row-num"></td>

        <td>
            <input
                type="text"
                class="form-control form-control-sm row-item"
                placeholder="الصنف"
                disabled
                data-lookup="item"
                data-lookup-display-field="itemName2"
                autocomplete="off"
            >
        </td>

        <td>
            <input
                type="text"
                class="form-control form-control-sm row-type"
                placeholder="النوع"
                disabled
                data-lookup="type"
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
                class="form-control form-control-sm row-weight"
                placeholder="العدد"
                min="0"
                step="0.001"
                disabled
                oninput="calculateRow(this)"
            >
        </td>

        <td>
            <input
                type="number"
                class="form-control form-control-sm row-price"
                value="0"
                min="0"
                step="0.01"
                disabled
                oninput="calculateRow(this)"
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
                oninput="calculateRow(this)"
            >
        </td>

        <td>
            <input
                type="number"
                class="form-control form-control-sm row-total"
                value="0.00"
                readonly
            >
        </td>

        <td>
            <button
                type="button"
                class="btn btn-sm btn-outline-danger"
                onclick="removeRow(this)"
                disabled
            >
                <i class="bi bi-trash3"></i>
            </button>
        </td>

    </tr>
</template>