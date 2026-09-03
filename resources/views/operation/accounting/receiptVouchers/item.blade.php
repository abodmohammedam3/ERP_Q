<!-- =========================================================
     بيانات سند القبض (متوافق مع المكونات العامة)
     ========================================================= -->

<div class="card mb-3">
    <div class="card-body">
        <form id="receiptVoucherForm" novalidate>
            <div class="row g-3">

                <!-- رقم السند (مخفي) -->
                <input type="hidden" id="ReceiptVoucherNumber" name="voucherNumber" value="">

                <!-- التاريخ (مخفي) -->
                <input type="hidden" id="ReceiptVoucherDate" name="voucherDate" value="">

                <!-- العميل -->
                <div class="col-md-4">
                    <label for="CustomerName" class="form-label">العميل <span class="text-danger">*</span></label>
                    <input type="hidden" id="CustomerID" name="customerID">
                    <input type="text" class="form-control" id="CustomerName" name="customerName"
                           placeholder="اكتب اسم العميل أو رقمه المحاسبي" autocomplete="off"
                           disabled
                           onkeydown="Customer.keyDown(event)" onblur="Customer.blur()">
                    <div class="invalid-feedback">يرجى اختيار العميل.</div>
                </div>

                <!-- المبلغ -->
                <div class="col-md-3">
                    <label for="Amount" class="form-label">المبلغ <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="Amount" name="amount"
                           placeholder="0.00" step="0.01" min="0" required
                           disabled oninput="updateSummary()">
                    <div class="invalid-feedback">المبلغ مطلوب وقيمته يجب أن تكون أكبر من صفر.</div>
                </div>

                <!-- العملة -->
                <div class="col-md-3">
                    <label for="CurrencyName" class="form-label">العملة</label>
                    <input type="hidden" id="CoinsID" name="coinsID">
                    <input type="text" class="form-control" id="CurrencyName" name="currencyName"
                           placeholder="اختر العملة" autocomplete="off"
                           disabled
                           onkeydown="Currency.keyDown(event)" onblur="Currency.blur()">
                </div>

                <!-- سعر الصرف -->
                <div class="col-md-2">
                    <label for="ExchangeRate" class="form-label">سعر الصرف</label>
                    <input type="number" step="0.000001" class="form-control" id="ExchangeRate" name="exchangeRate"
                           disabled oninput="updateSummary()">
                </div>

                <!-- طريقة الدفع -->
                <div class="col-md-3">
                    <label for="PaymentMethod" class="form-label">طريقة الدفع <span class="text-danger">*</span></label>
                    <select class="form-select" id="PaymentMethod" name="paymentMethod" disabled>
                        <option value="">اختر طريقة الدفع</option>
                        <option value="cash">نقد</option>
                        <option value="cheque">شيك</option>
                        <option value="bank">تحويل بنكي</option>
                        <option value="network">عبر شبكة</option>
                    </select>
                    <div class="invalid-feedback">يرجى اختيار طريقة الدفع.</div>
                </div>

                <!-- الصندوق (نقد) -->
                <div class="col-md-3 d-none" id="cashAccountContainer">
                    <label for="CashAccount" class="form-label">الصندوق</label>
                    <select class="form-select" id="CashAccount" name="cashAccount" disabled>
                        <option value="">اختر الصندوق</option>
                        <option value="main">الصندوق الرئيسي</option>
                    </select>
                </div>

                <!-- رقم الشيك -->
                <div class="col-md-3 d-none" id="chequeAccountContainer">
                    <label for="ChequeAccount" class="form-label">رقم الشيك</label>
                    <input type="text" class="form-control" id="ChequeAccount" name="chequeAccount"
                           placeholder="رقم الشيك" disabled>
                </div>

                <!-- الحساب البنكي -->
                <div class="col-md-3 d-none" id="bankAccountContainer">
                    <label for="BankAccount" class="form-label">حساب البنك</label>
                    <select class="form-select" id="BankAccount" name="bankAccount" disabled>
                        <option value="">اختر الحساب البنكي</option>
                        <option value="main-bank">الحساب البنكي الرئيسي</option>
                    </select>
                </div>

                <!-- حساب الشبكة -->
                <div class="col-md-3 d-none" id="walletAccountContainer">
                    <label for="WalletAccount" class="form-label">حساب المحفظة</label>
                    <select class="form-select" id="WalletAccount" name="walletAccount" disabled>
                        <option value="">اختر حساب المحفظة</option>
                        <option value="main-wallet">المحفظة الرئيسية</option>
                    </select>
                </div>

                

                <!-- البيان / الملاحظات -->
                <div class="col-12">
                    <label for="Notes" class="form-label">البيان / الملاحظات</label>
                    <input type="text" class="form-control" id="Notes" name="notes"
                           placeholder="سبب القبض أو وصف العملية" disabled>
                </div>

            </div>
        </form>
    </div>
</div>


<!-- =========================================================
     ملخص السند
     ========================================================= -->

<div class="row g-3">
    <!-- المبلغ كتابة -->
    <div class="col-md-8">
        <div class="card h-100">
            <div class="card-body">
                <label class="form-label" for="AmountWords">المبلغ كتابة</label>
                <input type="text" class="form-control amount-words" id="AmountWords" name="amountWords" readonly>
            </div>
        </div>
    </div>

    <!-- ملخص الرصيد -->
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


<!-- =========================================================
     مودالات (سيتم تضمينها من index.blade.php)
     ========================================================= -->