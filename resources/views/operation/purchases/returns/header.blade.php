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
                    readonly
                    tabindex="-1"
                >
            </div>

            <!-- الفاتورة الأصلية -->
            <div class="col-md-6">
                <label for="originalPurchaseInvoiceNo" class="form-label">الفاتورة الأصلية</label>
                <input type="hidden" id="originalPurchaseInvoiceId" name="originalPurchaseInvoiceId">
                <div
                    id="originalPurchaseInvoiceWrapper"
                    onclick="onOriginalInvoiceFieldClick()"
                >
                    <input
                        type="text"
                        class="form-control"
                        id="originalPurchaseInvoiceNo"
                        placeholder="اضغط لاختيار الفاتورة الأصلية..."
                        readonly
                        style="pointer-events: none; background-color: #fff;"
                    >
                </div>
            </div>

            <!-- طريقة الدفع -->
            <div class="col-md-3">
                <label for="PurchaseReturnPaymentMethod" class="form-label">طريقة الدفع</label>
                <input type="hidden" id="PurchaseReturnPaymentMethodValue">
                <input
                    type="text"
                    class="form-control"
                    id="PurchaseReturnPaymentMethod"
                    placeholder="تحدد من الفاتورة"
                    readonly
                    tabindex="-1"
                >
            </div>

            <!-- حساب الدفع (يظهر فقط عند نقدي/بنك) -->
            <div class="col-md-3 d-none" id="purchaseReturnPaymentAccountContainer">
                <label for="purchaseReturnPaymentAccount" class="form-label" id="purchaseReturnPaymentAccountLabel">الحساب</label>
                <input type="hidden" id="purchaseReturnPaymentAccountId">
                <input
                    type="text"
                    class="form-control"
                    id="purchaseReturnPaymentAccount"
                    readonly
                    tabindex="-1"
                    placeholder="—"
                >
            </div>

            <!-- المورد -->
            <div class="col-md-4">
                <label for="purchaseReturnSupplierName" class="form-label">المورد</label>
                <input type="hidden" id="purchaseReturnSupplierID" name="purchaseReturnSupplierID">
                <input
                    type="text"
                    class="form-control"
                    id="purchaseReturnSupplierName"
                    readonly
                    tabindex="-1"
                    placeholder="يتحدد عند اختيار الفاتورة"
                >
            </div>

            <!-- المخزن -->
            <div class="col-md-3">
                <label for="purchaseReturnWarehouseName" class="form-label">المخزن</label>
                <input type="hidden" id="purchaseReturnWarehouseID" name="purchaseReturnWarehouseID">
                <input
                    type="text"
                    class="form-control"
                    id="purchaseReturnWarehouseName"
                    readonly
                    tabindex="-1"
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
                    readonly
                    tabindex="-1"
                    placeholder="تحدد تلقائياً"
                >
            </div>

            <!-- ✅ سعر الصرف — readonly غير قابل للإدخال نهائيًا -->
            <div class="col-md-2">
                <label for="PurchaseReturnExchangeRate" class="form-label">سعر الصرف</label>
                <input
                    type="text"
                    inputmode="none"
                    class="form-control bg-light"
                    id="PurchaseReturnExchangeRate"
                    readonly
                    tabindex="-1"
                    onkeydown="return false"
                    onpaste="return false"
                    oncut="return false"
                    ondrop="return false"
                >
            </div>

        </div>

    </div>

</div>

<style>
    /* إخفاء أسهم الأرقام في حقل سعر الصرف */
    #PurchaseReturnExchangeRate::-webkit-outer-spin-button,
    #PurchaseReturnExchangeRate::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
</style>