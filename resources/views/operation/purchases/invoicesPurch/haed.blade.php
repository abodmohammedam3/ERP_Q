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

            <!-- رقم الفاتورة (تلقائي) -->
            <div class="col-md-3">

                <label
                    for="PurchaseInvoicesON2"
                    class="form-label"
                >
                    رقم الفاتورة
                </label>

                <input
                    type="text"
                    class="form-control"
                    id="PurchaseInvoicesON2"
                    name="PurchaseInvoicesON2"
                    readonly
                >

            </div>

            <!-- التاريخ -->
            <div class="col-md-3">

                <label
                    for="PurchaseInvoicesDate2"
                    class="form-label"
                >
                    التاريخ
                </label>

                <input
                    type="date"
                    class="form-control"
                    id="PurchaseInvoicesDate2"
                    name="PurchaseInvoicesDate2"
                    disabled
                >

            </div>

            <!-- طريقة الدفع -->
            <div class="col-md-3">

                <label
                    for="PuInPaymentMethod2"
                    class="form-label"
                >
                    طريقة الدفع
                </label>

                <select
                    class="form-select"
                    id="PuInPaymentMethod2"
                    name="PuInPaymentMethod2"
                    disabled
                    onchange="paymentMethodChanged()"
                >

                    <option value="">
                        اختر طريقة الدفع
                    </option>

                    <option value="credit">أجل</option>
                    <option value="cash">نقد</option>
                    <option value="bank">تحويل بنكي</option>
                    <option value="network">عبر شبكة</option>

                </select>

            </div>

            <!-- حساب الدفع (ديناميكي) -->
            <div
                class="col-md-3 d-none"
                id="paymentAccountContainer"
            >

                <label
                    for="paymentAccount"
                    class="form-label"
                >
                    الحساب
                </label>

                <div class="input-group">

                    <input
                        type="text"
                        class="form-control"
                        id="paymentAccount"
                        name="paymentAccount"
                        placeholder="اختر الحساب"
                        autocomplete="off"
                        disabled
                        onkeydown="accountKeyDown(event)"
                        onblur="accountBlur()"
                    >

                    <input type="hidden" id="paymentAccountId">

                </div>

            </div>

            <!-- المورد -->
            <div class="col-md-4">

                <label
                    for="supplierName"
                    class="form-label"
                >
                    المورد
                </label>

                <div class="input-group">

                    <input
                        type="hidden"
                        id="suplierID"
                        name="suplierID"
                    >

                    <input
                        type="text"
                        class="form-control"
                        id="supplierName"
                        name="supplierName"
                        placeholder="اكتب اسم المورد أو رقمه المحاسبي"
                        autocomplete="off"
                        disabled
                        onkeydown="supplierKeyDown(event)"
                        onblur="supplierBlur()"
                    >

                </div>

            </div>

            <!-- العملة -->
            <div class="col-md-3">

                <label
                    for="currencyName"
                    class="form-label"
                >
                    العملة
                </label>

                <input
                    type="hidden"
                    id="coinsID"
                    name="coinsID"
                >

                <div class="input-group">

                    <input
                        type="text"
                        class="form-control"
                        id="currencyName"
                        name="currencyName"
                        placeholder="اختر العملة"
                        autocomplete="off"
                        disabled
                        onkeydown="currencyKeyDown(event)"
                        onblur="currencyBlur()"
                    >

                </div>

            </div>

            <!-- سعر الصرف -->
            <div class="col-md-2">

                <label
                    for="PuInExchangeRate2"
                    class="form-label"
                >
                    سعر الصرف
                </label>

                <input
                    type="number"
                    step="0.000001"
                    class="form-control"
                    id="PuInExchangeRate2"
                    name="PuInExchangeRate2"
                    disabled
                    oninput="exchangeRateChanged()"
                >

            </div>

            <!-- المخزن -->
            <div class="col-md-3">

                <label
                    for="warehouseName"
                    class="form-label"
                >
                    المخزن
                </label>

                <input
                    type="hidden"
                    id="warehouseID"
                    name="warehouseID"
                >

                <div class="input-group">

                    <input
                        type="text"
                        class="form-control"
                        id="warehouseName"
                        name="warehouseName"
                        placeholder="اختر المخزن"
                        autocomplete="off"
                        disabled
                        onkeydown="warehouseKeyDown(event)"
                        onblur="warehouseBlur()"
                    >

                </div>

            </div>

        </div>

    </div>

</div>