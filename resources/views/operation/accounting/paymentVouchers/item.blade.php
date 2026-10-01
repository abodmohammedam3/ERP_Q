<!-- =========================================================
     بيانات سند الصرف
     المستفيد = مدين
     حساب الدفع = دائن
     ========================================================= -->

<div class="card mb-3">

    <div class="card-body">

        <form id="paymentVoucherForm"
              novalidate>

            <input type="hidden"
                   id="PaymentVoucherNumber"
                   name="voucherNumber"
                   value="">

            <input type="hidden"
                   id="PaymentVoucherDate"
                   name="voucherDate"
                   value="">

            <div class="row g-3 mb-3">

                <!-- الحساب المستفيد -->
                <div class="col-md-6">

                    <label for="BeneficiaryAccountName"
                           class="form-label">

                        الحساب المستفيد
                        <span class="text-danger">*</span>

                    </label>

                    <input type="hidden"
                           id="BeneficiaryAccountID"
                           name="beneficiaryAccountID">

                    <input type="text"
                           class="form-control"
                           id="BeneficiaryAccountName"
                           name="beneficiaryAccountName"
                           placeholder="اضغط لاختيار المستفيد..."
                           autocomplete="off"
                           readonly
                           style="cursor: pointer;">

                    <div class="invalid-feedback">
                        يرجى اختيار الحساب المستفيد.
                    </div>

                </div>

                <!-- طريقة الدفع -->
                <div class="col-md-3">

                    <label for="PaymentMethod"
                           class="form-label">

                        طريقة الدفع
                        <span class="text-danger">*</span>

                    </label>

                    <select class="form-select"
                            id="PaymentMethod"
                            name="paymentMethod">

                        <option value="">
                            اختر طريقة الدفع
                        </option>

                        <option value="cash">
                            نقد
                        </option>

                        <option value="bank">
                            تحويل بنكي
                        </option>

                    </select>

                    <div class="invalid-feedback">
                        يرجى اختيار طريقة الدفع.
                    </div>

                </div>

                <!-- حساب الدفع -->
                <div class="col-md-3 d-none"
                     id="paymentAccountContainer">

                    <label for="PaymentAccountName"
                           class="form-label">

                        <span id="paymentAccountLabel">
                            حساب الدفع
                        </span>

                        <span class="text-danger">*</span>

                    </label>

                    <input type="hidden"
                           id="PaymentAccountID"
                           name="paymentAccountID">

                    <input type="text"
                           class="form-control"
                           id="PaymentAccountName"
                           name="paymentAccountName"
                           placeholder="اضغط لاختيار حساب الدفع..."
                           autocomplete="off"
                           readonly
                           style="cursor: pointer;">

                    <div class="invalid-feedback">
                        يرجى اختيار حساب الدفع.
                    </div>

                </div>

            </div>

            <div class="row g-3 mb-3">

                <!-- العملة -->
                <div class="col-md-3">

                    <label for="CoinsID"
                           class="form-label">

                        العملة
                        <span class="text-danger">*</span>

                    </label>

                    <select class="form-select"
                            id="CoinsID"
                            name="coinsID">

                        <option value="">
                            اختر العملة
                        </option>

                    </select>

                    <div class="invalid-feedback">
                        يرجى اختيار العملة.
                    </div>

                </div>

                <!-- سعر الصرف -->
                <div class="col-md-3">

                    <label for="ExchangeRate"
                           class="form-label">

                        سعر الصرف

                    </label>

                    <input type="number"
                           step="0.000001"
                           class="form-control"
                           id="ExchangeRate"
                           name="exchangeRate"
                           readonly
                           tabindex="-1"
                           style="background-color: var(--bs-tertiary-bg); cursor: not-allowed;">

                </div>

                <!-- المبلغ -->
                <div class="col-md-3">

                    <label for="Amount"
                           class="form-label">

                        المبلغ
                        <span class="text-danger">*</span>

                    </label>

                    <input type="number"
                           class="form-control"
                           id="Amount"
                           name="amount"
                           placeholder="0.00"
                           step="0.01"
                           min="0"
                           required
                           oninput="updateSummary()">

                    <div class="invalid-feedback">
                        المبلغ مطلوب وقيمته يجب أن تكون أكبر من صفر.
                    </div>

                </div>

            </div>

            <div class="row g-3">

                <div class="col-12">

                    <label for="Notes"
                           class="form-label">

                        البيان / الملاحظات

                    </label>

                    <input type="text"
                           class="form-control"
                           id="Notes"
                           name="notes"
                           placeholder="سبب الصرف أو وصف العملية">

                </div>

            </div>

        </form>

    </div>

</div>


<!-- =========================================================
     الملخص
     ========================================================= -->

<div class="row g-3">

    <!-- المبلغ كتابة -->
    <div class="col-md-8">

        <div class="card h-100">

            <div class="card-body">

                <label class="form-label"
                       for="AmountWords">

                    المبلغ كتابة

                </label>

                <input type="text"
                       class="form-control amount-words"
                       id="AmountWords"
                       name="amountWords"
                       readonly>

            </div>

        </div>

    </div>


    <!-- الرصيد -->
    <div class="col-md-4">

        <div class="card h-100 summary-card">

            <div class="card-body d-flex flex-column justify-content-center">

                <div class="d-flex justify-content-between">

                    <span class="text-muted">
                        الرصيد السابق:
                    </span>

                    <strong class="total-amount"
                            id="SummaryPrevious">

                        0.00

                    </strong>

                </div>

                <div class="d-flex justify-content-between border-top pt-2 mt-2">

                    <span class="text-muted">
                        المبلغ المصروف:
                    </span>

                    <span class="text-danger fw-bold"
                          id="SummaryPaid">

                        0.00

                    </span>

                </div>

                <div class="d-flex justify-content-between border-top pt-2 mt-2">

                    <span class="text-muted">
                        الرصيد الحالي:
                    </span>

                    <span class="text-danger fw-bold"
                          id="SummaryRemain">

                        0.00

                    </span>

                </div>

            </div>

        </div>

    </div>

</div>