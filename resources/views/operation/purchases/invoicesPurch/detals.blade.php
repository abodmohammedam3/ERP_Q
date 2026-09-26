<style>
    .amount-foreign {
        font-size: 0.75rem;
        color: #6c757d;
        direction: ltr;
        text-align: right;
        line-height: 1.2;
        font-weight: 400;
    }

    .amount-local {
        font-size: 1rem;
        font-weight: 700;
        color: #212529;
        direction: ltr;
        text-align: right;
        line-height: 1.3;
    }

    .amount-single {
        font-size: 1rem;
        font-weight: 700;
        color: #212529;
        direction: ltr;
        text-align: right;
    }

    .totals-box {
        display: flex;
        align-items: stretch;
        gap: 0;
        border: 1px solid #dee2e6;
        border-radius: 0.5rem;
        background: #fff;
        overflow: hidden;
    }

    .totals-item {
        flex: 1;
        padding: 10px 16px;
        text-align: center;
        border-left: 1px solid #e9ecef;
    }

    .totals-item:first-child { border-left: none; }

    .totals-label {
        display: block;
        font-size: 0.9rem;
        font-weight: 700;
        margin-bottom: 4px;
    }

    .totals-item.discount .totals-label { color: #dc3545; }
    .totals-item.total    .totals-label { color: #198654; }

    .amount-words {
        font-size: 0.85rem;
    }

    input[type="number"]::-webkit-outer-spin-button,
    input[type="number"]::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    input[type="number"] {
        -moz-appearance: textfield;
        appearance: textfield;
    }
</style>


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
                    <th style="width: 11%;">الكمية</th>
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

            <tfoot>
                <tr>
                    <td colspan="10" class="p-3 bg-light">
                        <div class="row g-3 align-items-center">

                            <div class="col-md-6">
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

                            <div class="col-md-6">
                                <div class="totals-box">

                                    <div class="totals-item discount">
                                        <span class="totals-label">إجمالي الخصم</span>
                                        <div class="amount-foreign" id="totalDiscountForeign">0.00</div>
                                        <div class="amount-local"   id="totalDiscountLocal">0.00</div>
                                    </div>

                                    <div class="totals-item total">
                                        <span class="totals-label">إجمالي الفاتورة</span>
                                        <div class="amount-foreign" id="invoiceTotalForeign">0.00</div>
                                        <div class="amount-local"   id="invoiceTotalLocal">0.00</div>
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

            {{-- ✅ #3: تم حذف حقل النفقات من الواجهة --}}
            <input type="hidden" id="PuInExpenses" name="PuInExpenses" value="0">

            <div class="col-md-4">
                <label for="PuInTaxCost" class="form-label">تكلفة الضريبة</label>
                <input
                    type="number"
                    step="0.01"
                    min="0"
                    class="form-control"
                    id="PuInTaxCost"
                    name="PuInTaxCost"
                    disabled
                    oninput="calculateTotals()"
                >
            </div>

            <div class="col-md-4">
                <label for="PuInTransportation" class="form-label">تكلفة النقل</label>
                <input
                    type="number"
                    step="0.01"
                    min="0"
                    class="form-control"
                    id="PuInTransportation"
                    name="PuInTransportation"
                    disabled
                    oninput="calculateTotals()"
                >
            </div>

            <div class="col-md-4">
                <label for="PuInOtherCost" class="form-label">تكاليف أخرى</label>
                <input
                    type="number"
                    step="0.01"
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

            {{-- مجموع التكاليف الإضافية --}}
            <div class="col-md-12">
                <div class="d-flex justify-content-end">
                    <div class="d-flex align-items-center gap-2 border rounded-3 bg-white p-2 px-3">
                        <span class="text-primary small fw-bold">
                            <i class="bi bi-plus-circle me-1"></i>
                            مجموع التكاليف الإضافية
                        </span>
                        <strong class="text-primary fs-5" id="extraCostsTotal">0.00</strong>
                    </div>
                </div>
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
{{-- قالب صف تفاصيل الفاتورة --}}
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
                placeholder="الكمية"
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