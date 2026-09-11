<div class="modal fade" id="bankModal" tabindex="-1" data-bs-focus="false" aria-labelledby="bankModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header position-relative">
                <h5 class="modal-title" id="bankModalLabel">
                    <i class="bi bi-bank"></i>
                    إضافة بنك
                </h5>
                <button type="button" class="btn-close position-absolute top-0 start-0 m-3 " data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <form id="bankForm">
                    <input type="hidden" id="bankID">

                    <div class="row g-3">

                        <div class="col-md-6">
                            <label for="bankName" class="form-label">
                                اسم البنك <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="bankName" 
                                   placeholder="مثال: بنك التضامن">
                        </div>

                        <div class="col-md-6">
                            <label for="accountCode" class="form-label">رقم الحساب المحاسبي</label>
                            <input type="text" class="form-control" id="accountCode" readonly
                                   placeholder="سيتم إنشاؤه تلقائياً">
                            <div class="form-text">يتم توليده تلقائياً ولا يمكن تعديله.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="coinsID" class="form-label">
                                العملة <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="coinsID" onchange="updateExchangeRate()">
                                <option value="">اختر العملة</option>
                                @foreach($coins ?? [] as $coin)
                                    <option value="{{ $coin->coinsID }}"
                                            data-rate="{{ $coin->coinsExchangeRate }}"
                                            data-code="{{ $coin->coinsCode }}">
                                        {{ $coin->coinsCode }} - {{ $coin->coinsName }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text text-warning" id="coinWarning" style="display:none;">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                هذا البنك بلا عملة — يرجى اختيار العملة.
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="exchangeRate" class="form-label">سعر الصرف</label>
                            <input type="text" class="form-control" id="exchangeRate" readonly
                                   placeholder="يتم جلبه من العملة">
                        </div>

                        <div class="col-md-6">
                            <label for="accountNumber" class="form-label">رقم الحساب البنكي</label>
                            <input type="text" class="form-control" id="accountNumber"
                                   maxlength="50" placeholder="مثال: 1234567890">
                            <div class="form-text">رقم الحساب لدى البنك نفسه.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="isActive" class="form-label">الحالة</label>
                            <select class="form-select" id="isActive">
                                <option value="1">نشط</option>
                                <option value="0">غير نشط</option>
                            </select>
                        </div>

                    </div>
                </form>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg"></i> إلغاء
                </button>
                <button type="button" class="btn btn-primary" onclick="saveBank()">
                    <i class="bi bi-check-lg"></i> حفظ البيانات
                </button>
            </div>

        </div>
    </div>
</div>