<!-- ========================= -->
<!-- رأس الفاتورة -->
<!-- ========================= -->

<div class="card mb-3">

    <div class="card-header">
        <strong>
            <i class="bi bi-receipt"></i>
            بيانات الفاتورة
        </strong>
    </div>

    <div class="card-body">

        <div class="row g-3">

            <!-- رقم الفاتورة -->
            <div class="col-md-3">
                <label for="PurchaseInvoicesON2" class="form-label">رقم الفاتورة</label>
                <input
                    type="text"
                    class="form-control"
                    id="PurchaseInvoicesON2"
                    readonly
                >
            </div>

            <!-- التاريخ -->
            <div class="col-md-3">
                <label for="PurchaseInvoicesDate2" class="form-label">التاريخ</label>
                <input
                    type="date"
                    class="form-control"
                    id="PurchaseInvoicesDate2"
                    disabled
                >
            </div>

            <!-- طريقة الدفع -->
            <div class="col-md-3">
                <label for="PuInPaymentMethod2" class="form-label">طريقة الدفع</label>
                <select
                    class="form-select"
                    id="PuInPaymentMethod2"
                    disabled
                    onchange="paymentMethodChanged(true)"
                >
                    <option value="">اختر طريقة الدفع</option>
                    <option value="credit">أجل</option>
                    <option value="cash">نقدي (صندوق)</option>
                    <option value="bank">بنك</option>
                </select>
            </div>

            <!-- حساب الدفع -->
            <div class="col-md-3 d-none" id="paymentAccountContainer">
                <label for="paymentAccount" class="form-label">الحساب</label>
                <input type="hidden" id="paymentAccountId">
                <input
                    type="text"
                    class="form-control"
                    id="paymentAccount"
                    placeholder="اختر الحساب"
                    autocomplete="off"
                    disabled
                    data-lookup="box"
                    data-lookup-target="paymentAccountId"
                    data-lookup-id-field="accountID"
                    data-lookup-display-field="boxName"
                >
            </div>

            <!-- المورد -->
            <div class="col-md-4">
                <label for="supplierName" class="form-label">المورد</label>
                <input type="hidden" id="suplierID">
                <input
                    type="text"
                    class="form-control"
                    id="supplierName"
                    placeholder="اكتب اسم المورد أو رقمه المحاسبي"
                    autocomplete="off"
                    disabled
                    data-lookup="supplier"
                    data-lookup-target="suplierID"
                    data-lookup-id-field="accountID"
                    data-lookup-display-field="supName"
                    data-lookup-next="currencyName"
                >
            </div>

            <!-- العملة -->
            <div class="col-md-3">
                <label for="currencyName" class="form-label">العملة</label>
                <input type="hidden" id="coinsID">
                <input type="hidden" id="currencyCode">
                <input
                    type="text"
                    class="form-control"
                    id="currencyName"
                    placeholder="اختر العملة"
                    autocomplete="off"
                    disabled
                    data-lookup="currency"
                    data-lookup-target="coinsID"
                    data-lookup-id-field="coinsID"
                    data-lookup-display-field="coinsName"
                    data-lookup-next="warehouseName"
                >
            </div>

            <!-- ✅ سعر الصرف — readonly غير قابل للإدخال نهائيًا -->
            <div class="col-md-2">
                <label for="PuInExchangeRate2" class="form-label">سعر الصرف</label>
                <input
                    type="text"
                    inputmode="none"
                    class="form-control bg-light"
                    id="PuInExchangeRate2"
                    readonly
                    tabindex="-1"
                    onkeydown="return false"
                    onpaste="return false"
                    oncut="return false"
                    ondrop="return false"
                >
            </div>

            <!-- المخزن -->
            <div class="col-md-3">
                <label for="warehouseName" class="form-label">المخزن</label>
                <input type="hidden" id="warehouseID">
                <input
                    type="text"
                    class="form-control"
                    id="warehouseName"
                    placeholder="اختر المخزن"
                    autocomplete="off"
                    disabled
                    data-lookup="warehouse"
                    data-lookup-target="warehouseID"
                    data-lookup-id-field="StockID"
                    data-lookup-display-field="StockName"
                    data-lookup-next="purchaseItemFirstRow"
                >
            </div>

        </div>

    </div>

</div>

<style>
    /* إخفاء أسهم الأرقام في حقل سعر الصرف */
    #PuInExchangeRate2::-webkit-outer-spin-button,
    #PuInExchangeRate2::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
</style>