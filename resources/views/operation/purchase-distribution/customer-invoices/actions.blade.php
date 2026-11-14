<div class="card border-0 shadow-sm mt-3">
    <div class="card-body">
        <div class="row g-2 align-items-end mb-3">
            {{-- طريقة الدفع --}}
            <div class="col-md-6">
                <label class="form-label small text-muted mb-1">
                    <i class="bi bi-cash-coin"></i> طريقة الدفع
                </label>
                <div class="d-flex gap-2 flex-wrap" id="paymentMethodGroup">
                    {{-- نقدي = 2 --}}
                    <input type="radio" class="btn-check" name="paymentMethod" id="pmCash" value="2" checked>
                    <label class="btn btn-sm btn-outline-success" for="pmCash">
                        <i class="bi bi-cash"></i> نقدي
                    </label>

                    {{-- بنك = 3 --}}
                    <input type="radio" class="btn-check" name="paymentMethod" id="pmBank" value="3">
                    <label class="btn btn-sm btn-outline-primary" for="pmBank">
                        <i class="bi bi-bank"></i> بنك / محفظة / صراف
                    </label>

                    {{-- آجل = 1 --}}
                    <input type="radio" class="btn-check" name="paymentMethod" id="pmCredit" value="1">
                    <label class="btn btn-sm btn-outline-warning" for="pmCredit">
                        <i class="bi bi-clock-history"></i> آجل
                    </label>
                </div>
            </div>

            {{-- حساب الدفع --}}
            <div class="col-md-6" id="paymentAccountWrapper">
                <label class="form-label small text-muted mb-1">
                    <i class="bi bi-wallet2"></i> حساب الدفع
                </label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white">
                        <i class="bi bi-search text-muted"></i>
                    </span>
                    <input type="text"
                           class="form-control"
                           id="paymentAccountTrigger"
                           placeholder="اضغط لاختيار حساب..."
                           readonly
                           style="cursor:pointer; background-color:#f8f9fa;">
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center gap-2">
            <button class="btn btn-outline-danger btn-sm" id="btnCancel">
                <i class="bi bi-x-lg"></i> إلغاء العملية
            </button>
            <button class="btn btn-success px-4" id="btnIssue" disabled>
                <i class="bi bi-receipt"></i> إصدار الفاتورة
            </button>
        </div>
    </div>
</div>