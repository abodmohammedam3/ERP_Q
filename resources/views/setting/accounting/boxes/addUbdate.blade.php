<div class="modal fade"
     id="boxModal"
     tabindex="-1"
     data-bs-focus="false"
     aria-labelledby="boxModalLabel"
     aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header position-relative">
                <h5 class="modal-title" id="boxModalLabel">
                    <i class="bi bi-safe2"></i>
                    إضافة صندوق
                </h5>

                <button type="button"
                        class="btn-close position-absolute top-0 start-0 m-3"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                </button>
            </div>

            <div class="modal-body">

                <form id="boxForm">

                    <input type="hidden" id="boxID">

                    <div class="row g-3">

                        {{-- اسم الصندوق --}}
                        <div class="col-md-6">

                            <label for="boxName" class="form-label">
                                اسم الصندوق
                                <span class="text-danger">*</span>
                            </label>

                            <input type="text"
                                   class="form-control"
                                   id="boxName"
                                   maxlength="255"
                                   placeholder="مثال: الصندوق الرئيسي">

                        </div>

                        {{-- رقم الحساب --}}
                        <div class="col-md-6">

                            <label for="accountCode" class="form-label">
                                رقم الحساب التحليلي
                            </label>

                            <input type="text"
                                   class="form-control"
                                   id="accountCode"
                                   readonly
                                   placeholder="سيتم إنشاؤه تلقائياً">

                            <div class="form-text">
                                يتم توليده تلقائياً ولا يمكن تعديله.
                            </div>

                        </div>

                        {{-- العملة --}}
                        <div class="col-md-6">

                            <label for="coinsID" class="form-label">
                                العملة
                            </label>

                            <select class="form-select" id="coinsID"  onchange="updateExchangeRate()">

                                <option value="">
                                    اختر العملة
                                </option>

                                @foreach($coins ?? [] as $coin)

                                    <option value="{{ $coin->coinsID }}"
                                            data-rate="{{ $coin->coinsExchangeRate }}"
                                            data-code="{{ $coin->coinsCode }}">

                                        {{ $coin->coinsCode }} -
                                        {{ $coin->coinsName }}

                                    </option>

                                @endforeach

                            </select>

                            <div class="form-text text-muted">
                            يجب اختيار العملة المرتبطة بهذا الصندوق.
                            </div>

                        </div>

                        {{-- سعر الصرف --}}
                        <div class="col-md-6">

                            <label for="exchangeRate" class="form-label">
                                سعر الصرف
                            </label>

                            <input type="text"
                                   class="form-control"
                                   id="exchangeRate"
                                   readonly
                                   placeholder="يتم جلبه من العملة">

                        </div>

                        {{-- الحالة --}}
                        <div class="col-md-6">

                            <label for="isActive" class="form-label">
                                الحالة
                            </label>

                            <select class="form-select"
                                    id="isActive">

                                <option value="1">
                                    نشط
                                </option>

                                <option value="0">
                                    غير نشط
                                </option>

                            </select>

                        </div>

                    </div>

                </form>

            </div>

            <div class="modal-footer">

                <button type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal">

                    <i class="bi bi-x-lg"></i>
                    إلغاء

                </button>

                <button type="button"
                        class="btn btn-primary"
                        onclick="saveBox()">

                    <i class="bi bi-check-lg"></i>
                    حفظ البيانات

                </button>

            </div>

        </div>
    </div>

</div>