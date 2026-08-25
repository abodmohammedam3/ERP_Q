 <!-- ========================= -->
    <!-- رأس الفاتورة -->
    <!-- ========================= -->

    <div class="card mb-3">

        <div class="card-header">
            <strong>بيانات الفاتورة</strong>
        </div>

        <div class="card-body">

            <div class="row g-3">

                <!-- PurchaseInvoicesID -->
                <div class="col-md-2">
                    <label for="PurchaseInvoicesID" class="form-label">
                        رقم السجل
                    </label>

                    <input
                        type="number"
                        class="form-control"
                        id="PurchaseInvoicesID"
                        name="PurchaseInvoicesID"
                        readonly
                    >
                </div>


                <!-- PurchaseInvoicesON2 -->
                <div class="col-md-3">
                    <label for="PurchaseInvoicesON2" class="form-label">
                        رقم فاتورة الشراء
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        id="PurchaseInvoicesON2"
                        name="PurchaseInvoicesON2"
                    >
                </div>


                <!-- PurchaseInvoicesDate2 -->
                <div class="col-md-3">
                    <label for="PurchaseInvoicesDate2" class="form-label">
                        تاريخ الفاتورة
                    </label>

                    <input
                        type="date"
                        class="form-control"
                        id="PurchaseInvoicesDate2"
                        name="PurchaseInvoicesDate2"
                    >
                </div>


                <!-- suplierID -->
                <div class="col-md-4">
                    <label for="suplierID" class="form-label">
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
                            placeholder="اختر المورد"
                            readonly
                        >

                        <button
                            type="button"
                            class="btn btn-outline-primary"
                        >
                            اختيار
                        </button>

                    </div>
                </div>


                <!-- coinsID -->
                <div class="col-md-3">
                    <label for="coinsID" class="form-label">
                        العملة
                    </label>

                    <select
                        class="form-select"
                        id="coinsID"
                        name="coinsID"
                    >
                        <option value="">اختر العملة</option>
                    </select>
                </div>
                <!-- PuInPaymentMethod2 -->
                <div class="col-md-3">
                    <label for="PuInPaymentMethod2" class="form-label">
                        طريقة الدفع
                    </label>

                    <select
                        class="form-select"
                        id="PuInPaymentMethod2"
                        name="PuInPaymentMethod2"
                    >
                        <option value="">اختر طريقة الدفع</option>
                    </select>
                </div>


                <!-- PuInExchangeRate2 -->
                <div class="col-md-3">
                    <label for="PuInExchangeRate2" class="form-label">
                        سعر الصرف
                    </label>

                    <input
                        type="number"
                        step="0.000001"
                        class="form-control"
                        id="PuInExchangeRate2"
                        name="PuInExchangeRate2"
                    >
                </div>


                <!-- PuInWeight -->
                <div class="col-md-3">
                    <label for="PuInWeight" class="form-label">
                        إجمالي الوزن
                    </label>

                    <input
                        type="number"
                        step="0.001"
                        class="form-control"
                        id="PuInWeight"
                        name="PuInWeight"
                    >
                </div>


                <!-- PuInStatement2 -->
                <div class="col-12">
                    <label for="PuInStatement2" class="form-label">
                        البيان
                    </label>

                    <textarea
                        class="form-control"
                        id="PuInStatement2"
                        name="PuInStatement2"
                        rows="2"
                    ></textarea>
                </div>

            </div>

        </div>
    </div>