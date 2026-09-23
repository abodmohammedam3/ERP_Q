<!-- =========================================================
     مودال اختيار الحساب الموحد
     ========================================================= -->
<div class="modal fade" id="AccountPickerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <!-- رأس المودال -->
            <div class="modal-header">
                <h5 class="modal-title" id="AccountPickerTitle">اختيار الحساب</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <!-- جسم المودال -->
            <div class="modal-body">

                <!-- حقل البحث + زر تفريغ -->
                <div class="row g-2 mb-3">
                    <div class="col-md-10">
                        <input type="text" class="form-control" id="AccountPickerSearch"
                               placeholder="ابحث بالكود أو الاسم..." autocomplete="off" autofocus>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-outline-secondary w-100" id="AccountPickerClearBtn">
                            <i class="bi bi-x-lg"></i> تفريغ
                        </button>
                    </div>
                </div>

                <!-- جدول النتائج -->
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-bordered table-hover align-middle mb-0">
                        <thead class="table-light sticky-top">
                            <tr class="text-center">
                                <th style="width: 25%;" id="AccountPickerColCode">الكود</th>
                                <th id="AccountPickerColName">الاسم</th>
                                <th style="width: 25%;" id="AccountPickerColExtra">معلومة إضافية</th>
                            </tr>
                        </thead>
                        <tbody id="AccountPickerResults">
                            <tr><td colspan="3" class="text-center text-muted py-4">ابدأ البحث</td></tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>