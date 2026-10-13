
{{-- ========================= --}}
{{-- رأس مرتجع البيع --}}
{{-- ========================= --}}

<div class="card mb-3">

    <div class="card-header">
        <strong>
            <i class="bi bi-arrow-return-left text-danger me-1"></i>
            بيانات مرتجع البيع
        </strong>
    </div>

    <div class="card-body">

        <div class="row g-3">

            {{-- رقم المرتجع --}}
            <div class="col-md-3">
                <label for="SalesReturnNo" class="form-label">رقم المرتجع</label>
                <input
                    type="text"
                    class="form-control"
                    id="SalesReturnNo"
                    readonly
                    tabindex="-1"
                >
            </div>

            {{-- التاريخ --}}
            <div class="col-md-3">
                <label for="SalesReturnDate" class="form-label">التاريخ</label>
                <input
                    type="date"
                    class="form-control"
                    id="SalesReturnDate"
                    readonly
                    tabindex="-1"
                >
            </div>

            {{-- الفاتورة الأصلية --}}
            <div class="col-md-6">
                <label for="originalSalesInvoiceNo" class="form-label">
                    الفاتورة الأصلية
                </label>

                <input
                    type="hidden"
                    id="originalSalesInvoiceId"
                    name="originalSalesInvoiceId"
                >

                <div
                    id="originalSalesInvoiceWrapper"
                    onclick="onOriginalInvoiceFieldClick()"
                >
                    <input
                        type="text"
                        class="form-control"
                        id="originalSalesInvoiceNo"
                        placeholder="اضغط لاختيار الفاتورة الأصلية..."
                        readonly
                        style="pointer-events: none; background-color: #fff;"
                    >
                </div>
            </div>

            {{-- طريقة الدفع --}}
            <div class="col-md-3">
                <label for="SalesReturnPaymentMethod" class="form-label">
                    طريقة الدفع
                </label>

                <input
                    type="hidden"
                    id="SalesReturnPaymentMethodValue"
                >

                <input
                    type="text"
                    class="form-control"
                    id="SalesReturnPaymentMethod"
                    placeholder="تحدد من الفاتورة"
                    readonly
                    tabindex="-1"
                >
            </div>

            {{-- حساب الدفع: يظهر عند الحاجة --}}
            <div
                class="col-md-3 d-none"
                id="salesReturnPaymentAccountContainer"
            >
                <label
                    for="salesReturnPaymentAccount"
                    class="form-label"
                    id="salesReturnPaymentAccountLabel"
                >
                    الحساب
                </label>

                <input
                    type="hidden"
                    id="salesReturnPaymentAccountId"
                >

                <input
                    type="text"
                    class="form-control"
                    id="salesReturnPaymentAccount"
                    readonly
                    tabindex="-1"
                    placeholder="—"
                >
            </div>

            {{-- العميل --}}
            <div class="col-md-4">
                <label for="salesReturnCustomerName" class="form-label">
                    العميل
                </label>

                <input
                    type="hidden"
                    id="salesReturnCustomerID"
                    name="salesReturnCustomerID"
                >

                <input
                    type="text"
                    class="form-control"
                    id="salesReturnCustomerName"
                    readonly
                    tabindex="-1"
                    placeholder="يتحدد عند اختيار الفاتورة"
                >
            </div>

            {{-- المخزن --}}
            <div class="col-md-3">
                <label for="salesReturnWarehouseName" class="form-label">
                    المخزن
                </label>

                <input
                    type="hidden"
                    id="salesReturnWarehouseID"
                    name="salesReturnWarehouseID"
                >

                <input
                    type="text"
                    class="form-control"
                    id="salesReturnWarehouseName"
                    readonly
                    tabindex="-1"
                    placeholder="يتحدد تلقائياً"
                >
            </div>

            {{-- العملة --}}
            <div class="col-md-3">
                <label for="salesReturnCurrencyName" class="form-label">
                    العملة
                </label>

                <input
                    type="hidden"
                    id="salesReturnCoinsID"
                    name="salesReturnCoinsID"
                >

                <input
                    type="text"
                    class="form-control"
                    id="salesReturnCurrencyName"
                    readonly
                    tabindex="-1"
                    placeholder="تحدد تلقائياً"
                >
            </div>

            {{-- سعر الصرف: للعرض فقط --}}
            <div class="col-md-2">
                <label for="SalesReturnExchangeRate" class="form-label">
                    سعر الصرف
                </label>

                <input
                    type="text"
                    inputmode="none"
                    class="form-control bg-light"
                    id="SalesReturnExchangeRate"
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
    #SalesReturnExchangeRate::-webkit-outer-spin-button,
    #SalesReturnExchangeRate::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
</style>
