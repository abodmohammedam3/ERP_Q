<!-- ========================= -->
<!-- رأس مرتجع البيع -->
<!-- ========================= -->

<div class="card mb-3">

    <div class="card-header">
        <strong>
            <i class="bi bi-arrow-return-left text-danger me-1"></i>
            بيانات مرتجع البيع
        </strong>
    </div>

    <div class="card-body">

        <div class="row g-3">

            <!-- رقم المرتجع -->
            <div class="col-md-3">
                <label for="SalesReturnNo" class="form-label">رقم المرتجع</label>
                <input
                    type="text"
                    class="form-control"
                    id="SalesReturnNo"
                    name="SalesReturnNo"
                    readonly
                    tabindex="-1"
                >
            </div>

            <!-- التاريخ -->
            <div class="col-md-3">
                <label for="SalesReturnDate" class="form-label">التاريخ</label>
                <input
                    type="date"
                    class="form-control"
                    id="SalesReturnDate"
                    name="SalesReturnDate"
                    disabled
                >
            </div>

            <!-- الفاتورة الأصلية -->
            <div class="col-md-4">
                <label for="originalSalesInvoiceNo" class="form-label">الفاتورة الأصلية</label>
                <div class="input-group">
                    <input type="hidden" id="originalSalesInvoiceId" name="originalSalesInvoiceId">
                    <input
                        type="text"
                        class="form-control"
                        id="originalSalesInvoiceNo"
                        placeholder="اختر الفاتورة الأصلية..."
                        readonly
                    >
                    <button
                        type="button"
                        class="btn btn-outline-primary"
                        id="btnSelectOriginalInvoice"
                        onclick="searchOriginalInvoice()"
                        disabled
                    >
                        <i class="bi bi-search"></i>
                        اختيار
                    </button>
                </div>
            </div>

            <!-- طريقة الدفع -->
            <div class="col-md-2">
                <label for="SalesReturnPaymentMethod" class="form-label">طريقة الدفع</label>
                <select
                    class="form-select"
                    id="SalesReturnPaymentMethod"
                    name="SalesReturnPaymentMethod"
                    disabled
                    onchange="salesReturnPaymentMethodChanged()"
                >
                    <option value="">اختر طريقة الدفع</option>
                    <option value="credit">أجل</option>
                    <option value="cash">نقد</option>
                    <option value="bank">تحويل بنكي</option>
                    <option value="network">عبر شبكة</option>
                </select>
            </div>

            <!-- حساب الدفع (ديناميكي) -->
            <div class="col-md-3 d-none" id="salesReturnPaymentAccountContainer">
                <label for="salesReturnPaymentAccount" class="form-label">الحساب</label>
                <input type="hidden" id="salesReturnPaymentAccountId">
                <input
                    type="text"
                    class="form-control"
                    id="salesReturnPaymentAccount"
                    name="salesReturnPaymentAccount"
                    placeholder="اختر الحساب"
                    autocomplete="off"
                    disabled
                >
            </div>

            <!-- العميل (مأخوذ تلقائياً من الفاتورة) -->
            <div class="col-md-4">
                <label for="salesReturnCustomerName" class="form-label">العميل</label>
                <input type="hidden" id="salesReturnCustomerID" name="salesReturnCustomerID">
                <input
                    type="text"
                    class="form-control"
                    id="salesReturnCustomerName"
                    name="salesReturnCustomerName"
                    readonly
                    placeholder="يتحدد عند اختيار الفاتورة"
                >
            </div>

            <!-- العملة (مأخوذة تلقائياً من الفاتورة) -->
            <div class="col-md-3">
                <label for="salesReturnCurrencyName" class="form-label">العملة</label>
                <input type="hidden" id="salesReturnCoinsID" name="salesReturnCoinsID">
                <input
                    type="text"
                    class="form-control"
                    id="salesReturnCurrencyName"
                    name="salesReturnCurrencyName"
                    readonly
                    placeholder="تحدد تلقائياً"
                >
            </div>

            <!-- سعر الصرف -->
            <div class="col-md-2">
                <label for="SalesReturnExchangeRate" class="form-label">سعر الصرف</label>
                <input
                    type="number"
                    step="0.000001"
                    class="form-control"
                    id="SalesReturnExchangeRate"
                    name="SalesReturnExchangeRate"
                    readonly
                >
            </div>

        </div>

    </div>

</div>
