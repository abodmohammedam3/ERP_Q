 
     <!-- ===================================== -->
    <!-- بيانات رأس الفاتورة -->
    <!-- ===================================== -->
 
 
 <div class="card mb-3">

        <div class="card-header">
            <strong>بيانات فاتورة البيع</strong>
        </div>

        <div class="card-body">

            <div class="row g-3">

                <!-- SalesInvoicesID -->

                <div class="col-md-2">

                    <label
                        for="SalesInvoicesID"
                        class="form-label">

                        رقم السجل

                    </label>

                    <input
                        type="number"
                        class="form-control"
                        id="SalesInvoicesID"
                        name="SalesInvoicesID"
                        readonly>

                </div>


                <!-- SalesInvoicesON3 -->

                <div class="col-md-3">

                    <label
                        for="SalesInvoicesON3"
                        class="form-label">

                        رقم الفاتورة

                    </label>

                    <input
                        type="text"
                        class="form-control"
                        id="SalesInvoicesON3"
                        name="SalesInvoicesON3"
                        placeholder="رقم فاتورة البيع">

                </div>


                <!-- SalesInvoicesDate3 -->

                <div class="col-md-3">

                    <label
                        for="SalesInvoicesDate3"
                        class="form-label">

                        تاريخ الفاتورة

                    </label>

                    <input
                        type="date"
                        class="form-control"
                        id="SalesInvoicesDate3"
                        name="SalesInvoicesDate3">

                </div>


                <!-- CustomersID -->

                <div class="col-md-4">

                    <label
                        for="CustomersID"
                        class="form-label">

                        العميل

                    </label>

                    <div class="input-group">

                        <input
                            type="hidden"
                            id="CustomersID"
                            name="CustomersID">

                        <input
                            type="text"
                            class="form-control"
                            id="customerName"
                            name="customerName"
                            placeholder="اختر العميل"
                            readonly>

                        <button
                            type="button"
                            class="btn btn-outline-primary"
                            id="btnSelectCustomer">

                            اختيار

                        </button>

                    </div>

                </div>


                <!-- coinsID -->

                <div class="col-md-3">

                    <label
                        for="coinsID"
                        class="form-label">

                        العملة

                    </label>

                    <select
                        class="form-select"
                        id="coinsID"
                        name="coinsID">

                        <option value="">
                            اختر العملة
                        </option>

                    </select>

                </div>


                <!-- SalesInvoicesMethod3 -->

                <div class="col-md-3">

                    <label
                        for="SalesInvoicesMethod3"
                        class="form-label">

                        طريقة البيع / الدفع

                    </label>

                    <select
                        class="form-select"
                        id="SalesInvoicesMethod3"
                        name="SalesInvoicesMethod3">

                        <option value="">
                            اختر الطريقة
                        </option>

                    </select>

                </div>


                <!-- SalesExchangeRate3 -->

                <div class="col-md-3">

                    <label
                        for="SalesExchangeRate3"
                        class="form-label">

                        سعر الصرف

                    </label>

                    <input
                        type="number"
                        step="0.000001"
                        class="form-control"
                        id="SalesExchangeRate3"
                        name="SalesExchangeRate3">

                </div>


                <!-- الحساب المحاسبي للعميل -->

                <div class="col-md-3">

                    <label
                        for="customerAccountID"
                        class="form-label">

                        حساب العميل

                    </label>

                    <input
                        type="hidden"
                        id="customerAccountID"
                        name="customerAccountID">

                    <input
                        type="text"
                        class="form-control"
                        id="customerAccountName"
                        name="customerAccountName"
                        readonly>

                </div>


                <!-- SalesStatement3 -->

                <div class="col-12">

                    <label
                        for="SalesStatement3"
                        class="form-label">

                        البيان

                    </label>

                    <textarea
                        class="form-control"
                        id="SalesStatement3"
                        name="SalesStatement3"
                        rows="2"
                        placeholder="بيان الفاتورة"></textarea>

                </div>

            </div>

        </div>

    </div>