<!-- =========================================================
     بيانات سند القبض - النسخة النهائية
     ========================================================= -->

<div class="card mb-3">
    <div class="card-body">
        <form id="receiptVoucherForm" novalidate>

            <!-- رقم السند (مخفي) -->
            <input type="hidden" id="ReceiptVoucherNumber" name="voucherNumber" value="">
            <input type="hidden" id="ReceiptVoucherDate" name="voucherDate" value="">

            <!-- ═══════════════════════════════════════════════ -->
            <!--  الصف الأول: الحساب الدائن + طريقة الدفع + الحساب المدين -->
            <!-- ═══════════════════════════════════════════════ -->
            <div class="row g-3 mb-3">

                <div class="col-md-6">
                    <label for="CreditAccountName" class="form-label">
                        الحساب الدائن (العميل) <span class="text-danger">*</span>
                    </label>
                    <input type="hidden" id="CreditAccountID" name="creditAccountID">
                    <input type="text"
                           class="form-control"
                           id="CreditAccountName"
                           name="creditAccountName"
                           placeholder="اضغط لاختيار الحساب الدائن..."
                           autocomplete="off"
                           readonly
                           disabled>
                    <div class="invalid-feedback">يرجى اختيار الحساب الدائن.</div>
                </div>

                <div class="col-md-3">
                    <label for="PaymentMethod" class="form-label">
                        طريقة الدفع <span class="text-danger">*</span>
                    </label>
                    <select class="form-select" id="PaymentMethod" name="paymentMethod" disabled>
                        <option value="">اختر طريقة الدفع</option>
                        <option value="cash">نقد</option>
                        <option value="bank">تحويل بنكي</option>
                    </select>
                    <div class="invalid-feedback">يرجى اختيار طريقة الدفع.</div>
                </div>

                <div class="col-md-3 d-none" id="debitAccountContainer">
                    <label for="DebitAccountName" class="form-label">
                        <span id="debitAccountLabel">الحساب المدين</span>
                        <span class="text-danger">*</span>
                    </label>
                    <input type="hidden" id="DebitAccountID" name="debitAccountID">
                    <input type="text"
                           class="form-control"
                           id="DebitAccountName"
                           name="debitAccountName"
                           placeholder="اضغط لاختيار الحساب المدين..."
                           autocomplete="off"
                           readonly
                           disabled>
                    <div class="invalid-feedback">يرجى اختيار الحساب المدين.</div>
                </div>

            </div>

            <!-- ═══════════════════════════════════════════════ -->
            <!--  الصف الثاني: العملة + سعر الصرف + المبلغ -->
            <!-- ═══════════════════════════════════════════════ -->
            <div class="row g-3 mb-3">

                <div class="col-md-3">
                    <label for="CoinsID" class="form-label">العملة <span class="text-danger">*</span></label>
                    <select class="form-select" id="CoinsID" name="coinsID" disabled>
                        <option value="">اختر العملة</option>
                    </select>
                    <div class="invalid-feedback">يرجى اختيار العملة.</div>
                </div>

                <div class="col-md-3">
                    <label for="ExchangeRate" class="form-label">سعر الصرف</label>
                    <input type="number"
                           step="0.000001"
                           class="form-control"
                           id="ExchangeRate"
                           name="exchangeRate"
                           disabled
                           oninput="updateSummary()">
                </div>

                <div class="col-md-3">
                    <label for="Amount" class="form-label">
                        المبلغ <span class="text-danger">*</span>
                    </label>
                    <input type="number"
                           class="form-control"
                           id="Amount"
                           name="amount"
                           placeholder="0.00"
                           step="0.01"
                           min="0"
                           required
                           disabled
                           oninput="updateSummary()">
                    <div class="invalid-feedback">المبلغ مطلوب وقيمته يجب أن تكون أكبر من صفر.</div>
                </div>

            </div>

            <!-- ═══════════════════════════════════════════════ -->
            <!--  الصف الثالث: البيان / الملاحظات -->
            <!-- ═══════════════════════════════════════════════ -->
            <div class="row g-3">

                <div class="col-12">
                    <label for="Notes" class="form-label">البيان / الملاحظات</label>
                    <input type="text"
                           class="form-control"
                           id="Notes"
                           name="notes"
                           placeholder="سبب القبض أو وصف العملية"
                           disabled>
                </div>

            </div>

        </form>
    </div>
</div>


<!-- =========================================================
     ملخص السند
     ========================================================= -->

<div class="row g-3">
    <div class="col-md-8">
        <div class="card h-100">
            <div class="card-body">
                <label class="form-label" for="AmountWords">المبلغ كتابة</label>
                <input type="text"
                       class="form-control amount-words"
                       id="AmountWords"
                       name="amountWords"
                       readonly>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card h-100 summary-card">
            <div class="card-body d-flex flex-column justify-content-center">
                <div class="d-flex justify-content-between">
                    <span class="text-muted">الرصيد السابق:</span>
                    <strong class="total-amount" id="SummaryPrevious">0.00</strong>
                </div>
                <div class="d-flex justify-content-between border-top pt-2 mt-2">
                    <span class="text-muted">المبلغ المقبوض:</span>
                    <span class="text-success fw-bold" id="SummaryPaid">0.00</span>
                </div>
                <div class="d-flex justify-content-between border-top pt-2 mt-2">
                    <span class="text-muted">الرصيد الحالي:</span>
                    <span class="text-danger fw-bold" id="SummaryRemain">0.00</span>
                </div>
            </div>
        </div>
    </div>
</div>