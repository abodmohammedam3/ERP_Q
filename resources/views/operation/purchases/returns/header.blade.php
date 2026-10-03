<!-- ========================= -->
<!-- رأس مرتجع الشراء -->
<!-- ========================= -->

<div class="card mb-3">

    <div class="card-header">
        <strong>
            <i class="bi bi-arrow-return-right text-danger me-1"></i>
            بيانات مرتجع الشراء
        </strong>
    </div>

    <div class="card-body">

        <div class="row g-3">

            <!-- رقم المرتجع -->
            <div class="col-md-3">
                <label for="PurchaseReturnNo" class="form-label">رقم المرتجع</label>
                <input
                    type="text"
                    class="form-control"
                    id="PurchaseReturnNo"
                    name="PurchaseReturnNo"
                    readonly
                    tabindex="-1"
                >
            </div>

            <!-- التاريخ -->
            <div class="col-md-3">
                <label for="PurchaseReturnDate" class="form-label">التاريخ</label>
                <input
                    type="date"
                    class="form-control"
                    id="PurchaseReturnDate"
                    name="PurchaseReturnDate"
                    disabled
                >
            </div>

            <!-- الفاتورة الأصلية -->
            <div class="col-md-4">
                <label for="originalPurchaseInvoiceNo" class="form-label">الفاتورة الأصلية</label>
                <div class="input-group">
                    <input type="hidden" id="originalPurchaseInvoiceId" name="originalPurchaseInvoiceId">
                    <input
                        type="text"
                        class="form-control"
                        id="originalPurchaseInvoiceNo"
                        placeholder="اختر الفاتورة الأصلية..."
                        readonly
                    >
                    <button
                        type="button"
                        class="btn btn-outline-primary"
                        id="btnSelectOriginalPurchaseInvoice"
                        onclick="searchOriginalPurchaseInvoice()"
                        disabled
                    >
                        <i class="bi bi-search"></i>
                        اختيار
                    </button>
                </div>
            </div>

            <!-- طريقة الدفع -->
            <div class="col-md-2">
                <label for="PurchaseReturnPaymentMethod" class="form-label">طريقة الدفع</label>
                <select
                    class="form-select"
                    id="PurchaseReturnPaymentMethod"
                    name="PurchaseReturnPaymentMethod"
                    disabled
                    onchange="purchaseReturnPaymentMethodChanged()"
                >
                    <option value="">اختر طريقة الدفع</option>
                    <option value="credit">أجل</option>
                    <option value="cash">نقد</option>
                    <option value="bank">تحويل بنكي</option>
                    <option value="network">عبر شبكة</option>
                </select>
            </div>

            <!-- حساب الدفع (ديناميكي) -->
            <div class="col-md-3 d-none" id="purchaseReturnPaymentAccountContainer">
                <label for="purchaseReturnPaymentAccount" class="form-label">الحساب</label>
                <input type="hidden" id="purchaseReturnPaymentAccountId">
                <input
                    type="text"
                    class="form-control"
                    id="purchaseReturnPaymentAccount"
                    name="purchaseReturnPaymentAccount"
                    placeholder="اختر الحساب"
                    autocomplete="off"
                    disabled
                >
            </div>

            <!-- المورد (مأخوذ تلقائياً من الفاتورة) -->
            <div class="col-md-4">
                <label for="purchaseReturnSupplierName" class="form-label">المورد</label>
                <input type="hidden" id="purchaseReturnSupplierID" name="purchaseReturnSupplierID">
                <input
                    type="text"
                    class="form-control"
                    id="purchaseReturnSupplierName"
                    name="purchaseReturnSupplierName"
                    readonly
                    placeholder="يتحدد عند اختيار الفاتورة"
                >
            </div>

            <!-- المخزن الرئيس -->
            <div class="col-md-3">
                <label for="purchaseReturnWarehouseName" class="form-label">المخزن الرئيسي</label>
                <input type="hidden" id="purchaseReturnWarehouseID" name="purchaseReturnWarehouseID">
                <input
                    type="text"
                    class="form-control"
                    id="purchaseReturnWarehouseName"
                    name="purchaseReturnWarehouseName"
                    readonly
                    placeholder="يتحدد تلقائياً"
                >
            </div>

            <!-- العملة -->
            <div class="col-md-3">
                <label for="purchaseReturnCurrencyName" class="form-label">العملة</label>
                <input type="hidden" id="purchaseReturnCoinsID" name="purchaseReturnCoinsID">
                <input
                    type="text"
                    class="form-control"
                    id="purchaseReturnCurrencyName"
                    name="purchaseReturnCurrencyName"
                    readonly
                    placeholder="تحدد تلقائياً"
                >
            </div>

            <!-- سعر الصرف -->
            <div class="col-md-2">
                <label for="PurchaseReturnExchangeRate" class="form-label">سعر الصرف</label>
                <input
                    type="number"
                    step="0.000001"
                    class="form-control"
                    id="PurchaseReturnExchangeRate"
                    name="PurchaseReturnExchangeRate"
                    readonly
                >
            </div>

        </div>

    </div>

</div>
