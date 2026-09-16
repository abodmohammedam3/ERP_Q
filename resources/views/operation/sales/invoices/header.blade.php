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
                <label for="SalesInvoiceNo" class="form-label">رقم الفاتورة</label>
                <input
                    type="text"
                    class="form-control"
                    id="SalesInvoiceNo"
                    name="SalesInvoiceNo"
                    readonly
                    tabindex="-1"
                >
            </div>

            <!-- التاريخ -->
            <div class="col-md-3">
                <label for="SalesInvoiceDate" class="form-label">التاريخ</label>
                <input
                    type="date"
                    class="form-control"
                    id="SalesInvoiceDate"
                    name="SalesInvoiceDate"
                    disabled
                    data-no-focus
                >
            </div>

            <!-- طريقة الدفع -->
            <div class="col-md-3">
                <label for="SalesPaymentMethod" class="form-label">طريقة الدفع</label>
                <select
                    class="form-select"
                    id="SalesPaymentMethod"
                    name="SalesPaymentMethod"
                    disabled
                    onchange="salesPaymentMethodChanged(true)"
                >
                    <option value="">اختر طريقة الدفع</option>
                    <option value="credit">أجل</option>
                    <option value="cash">نقد</option>
                    <option value="bank">تحويل بنكي</option>
                    <option value="network">عبر شبكة</option>
                </select>
            </div>

            <!-- حساب الدفع (ديناميكي) -->
            <div class="col-md-3 d-none" id="salesPaymentAccountContainer">
                <label for="salesPaymentAccount" class="form-label">الحساب</label>
                <input type="hidden" id="salesPaymentAccountId">
                <input
                    type="text"
                    class="form-control"
                    id="salesPaymentAccount"
                    name="salesPaymentAccount"
                    placeholder="اختر الحساب"
                    autocomplete="off"
                    disabled
                    data-lookup="box"
                    data-lookup-target="salesPaymentAccountId"
                    data-lookup-id-field="accountID"
                    data-lookup-display-field="boxName"
                >
            </div>

            <!-- العميل -->
            <div class="col-md-4">
                <label for="customerName" class="form-label">العميل</label>
                <input type="hidden" id="customerID" name="customerID">
                <input
                    type="text"
                    class="form-control"
                    id="customerName"
                    name="customerName"
                    placeholder="اكتب اسم العميل أو رقمه المحاسبي"
                    autocomplete="off"
                    disabled
                    data-lookup="customer"
                    data-lookup-target="customerID"
                    data-lookup-id-field="accountID"
                    data-lookup-display-field="CustomersName2"
                    data-lookup-next="salesCurrencyName"
                >
            </div>

            <!-- العملة -->
            <div class="col-md-3">
                <label for="salesCurrencyName" class="form-label">العملة</label>
                <input type="hidden" id="salesCoinsID" name="salesCoinsID">
                <input
                    type="text"
                    class="form-control"
                    id="salesCurrencyName"
                    name="salesCurrencyName"
                    placeholder="اختر العملة"
                    autocomplete="off"
                    disabled
                    data-lookup="currency"
                    data-lookup-target="salesCoinsID"
                    data-lookup-id-field="coinsID"
                    data-lookup-display-field="coinsName"
                    data-lookup-next="SalesExchangeRate"
                >
            </div>

            <!-- سعر الصرف -->
            <div class="col-md-2">
                <label for="SalesExchangeRate" class="form-label">سعر الصرف</label>
                <input
                    type="number"
                    step="0.000001"
                    class="form-control"
                    id="SalesExchangeRate"
                    name="SalesExchangeRate"
                    disabled
                    oninput="salesExchangeRateChanged()"
                >
            </div>

        </div>

    </div>

</div>