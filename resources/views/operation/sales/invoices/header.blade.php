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


            <!-- رقم الفاتورة (للقراءة فقط) -->
            <div class="col-md-3">

                <label
                    for="SalesInvoiceNo"
                    class="form-label"
                >
                    رقم الفاتورة
                </label>

                <input
                    type="text"
                    class="form-control"
                    id="SalesInvoiceNo"
                    name="SalesInvoiceNo"
                    readonly
                >

            </div>


            <!-- التاريخ -->
            <div class="col-md-3">

                <label
                    for="SalesInvoiceDate"
                    class="form-label"
                >
                    التاريخ
                </label>

                <input
                    type="date"
                    class="form-control"
                    id="SalesInvoiceDate"
                    name="SalesInvoiceDate"
                    disabled
                >

            </div>


            <!-- طريقة الدفع -->
            <div class="col-md-3">

                <label
                    for="SalesPaymentMethod"
                    class="form-label"
                >
                    طريقة الدفع
                </label>

                <select
                    class="form-select"
                    id="SalesPaymentMethod"
                    name="SalesPaymentMethod"
                    disabled
                    onchange="salesPaymentMethodChanged()"
                >

                    <option value="">
                        اختر طريقة الدفع
                    </option>

                    <option value="credit">
                        أجل
                    </option>

                    <option value="cash">
                        نقد
                    </option>

                    <option value="bank">
                        تحويل بنكي
                    </option>

                    <option value="network">
                        عبر شبكة
                    </option>

                </select>

            </div>


            <!-- الصندوق -->
            <div
                class="col-md-3 d-none"
                id="salesCashAccountContainer"
            >

                <label
                    for="salesCashAccount"
                    class="form-label"
                >
                    الصندوق
                </label>

                <select
                    class="form-select"
                    id="salesCashAccount"
                    disabled
                >

                    <option value="">
                        اختر الصندوق
                    </option>

                    <option value="main">
                        الصندوق الرئيسي
                    </option>

                </select>

            </div>


            <!-- حسابات البنوك -->
            <div
                class="col-md-3 d-none"
                id="salesBankAccountContainer"
            >

                <label
                    for="salesBankAccount"
                    class="form-label"
                >
                    حسابات البنوك
                </label>

                <select
                    class="form-select"
                    id="salesBankAccount"
                    disabled
                >

                    <option value="">
                        اختر الحساب البنكي
                    </option>

                    <option value="main-bank">
                        الحساب البنكي الرئيسي
                    </option>

                </select>

            </div>


            <!-- حساب المحفظة -->
            <div
                class="col-md-3 d-none"
                id="salesWalletAccountContainer"
            >

                <label
                    for="salesWalletAccount"
                    class="form-label"
                >
                    حساب المحفظة
                </label>

                <select
                    class="form-select"
                    id="salesWalletAccount"
                    disabled
                >

                    <option value="">
                        اختر حساب المحفظة
                    </option>

                    <option value="main-wallet">
                        المحفظة الرئيسية
                    </option>

                </select>

            </div>


            <!-- العميل -->
            <div class="col-md-4">

                <label
                    for="customerName"
                    class="form-label"
                >
                    العميل
                </label>

                <div class="input-group">

                    <input
                        type="hidden"
                        id="customerID"
                        name="customerID"
                    >

                    <input
                        type="text"
                        class="form-control"
                        id="customerName"
                        name="customerName"
                        placeholder="اكتب اسم العميل أو رقمه المحاسبي"
                        autocomplete="off"
                        disabled
                        onkeydown="customerKeyDown(event)"
                        oninput="customerInput(event)"
                    >

                </div>

            </div>


            <!-- العملة -->
            <div class="col-md-3">

                <label
                    for="salesCurrencyName"
                    class="form-label"
                >
                    العملة
                </label>

                <input
                    type="hidden"
                    id="salesCoinsID"
                    name="salesCoinsID"
                >

                <input
                    type="text"
                    class="form-control"
                    id="salesCurrencyName"
                    name="salesCurrencyName"
                    placeholder="اختر العملة"
                    autocomplete="off"
                    disabled
                    onkeydown="salesCurrencyKeyDown(event)"
                    oninput="salesCurrencyInput(event)"
                >

            </div>


            <!-- سعر الصرف -->
            <div class="col-md-2">

                <label
                    for="SalesExchangeRate"
                    class="form-label"
                >
                    سعر الصرف
                </label>

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