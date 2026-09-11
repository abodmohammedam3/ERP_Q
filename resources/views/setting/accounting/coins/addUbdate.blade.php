{{-- مودال إضافة/تعديل عملة --}}
<div class="modal fade" id="coinModal" tabindex="-1" data-bs-focus="false" aria-labelledby="coinModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header position-relative">
                <h5 class="modal-title" id="coinModalLabel">
                    <i class="bi bi-currency-exchange"></i>
                    إضافة عملة
                </h5>
                <button type="button" class="btn-close position-absolute top-0 start-0 m-3 " data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <form id="coinForm">
                    <input type="hidden" id="coinID">

                    <div class="row g-3">

                        {{-- اسم العملة --}}
                        <div class="col-md-6">
                            <label for="coinsName" class="form-label">
                                اسم العملة <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="coinsName" required
                                   placeholder="مثال: الريال اليمني">
                        </div>

                        {{-- رمز العملة --}}
                        <div class="col-md-3">
                            <label for="coinsCode" class="form-label">
                                رمز العملة <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="coinsCode" required
                                   maxlength="10" placeholder="YER">
                        </div>

                        {{-- سعر الصرف --}}
                        <div class="col-md-3">
                            <label for="coinsExchangeRate" class="form-label">
                                سعر الصرف <span class="text-danger">*</span>
                            </label>
                            <input type="number" class="form-control" id="coinsExchangeRate"
                                   step="0.000001" min="0" required placeholder="1.000000">
                        </div>

                        {{-- عملة النظام --}}
                        <div class="col-md-6">
                            <label for="coinsSystem" class="form-label">عملة النظام الأساسية</label>
                            <select class="form-select" id="coinsSystem">
                                <option value="0">لا</option>
                                <option value="1">نعم</option>
                            </select>
                            <div class="form-text">عملة واحدة فقط يمكن أن تكون الأساسية.</div>
                        </div>

                        {{-- الحالة --}}
                        <div class="col-md-6">
                            <label for="isActive" class="form-label">الحالة</label>
                            <select class="form-select" id="isActive">
                                <option value="1">نشطة</option>
                                <option value="0">غير نشطة</option>
                            </select>
                        </div>

                    </div>
                </form>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg"></i> إلغاء
                </button>
                <button type="button" class="btn btn-primary" onclick="saveCoin()">
                    <i class="bi bi-check-lg"></i> حفظ البيانات
                </button>
            </div>

        </div>
    </div>
</div>