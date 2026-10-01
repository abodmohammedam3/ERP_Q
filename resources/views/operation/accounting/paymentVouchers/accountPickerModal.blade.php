{{-- ═══════════════════════════════════════════════════════════
     مودال اختيار الحساب الموحد (مع تبويبات)
     ═══════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="AccountPickerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">

            <!-- رأس المودال -->
            <div class="modal-header">
                <h5 class="modal-title" id="AccountPickerTitle">اختيار الحساب</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <!-- جسم المودال -->
            <div class="modal-body">

                <!-- التبويبات -->
                <ul class="nav nav-tabs mb-3" id="AccountPickerTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active"
                                data-type="customer"
                                type="button"
                                role="tab">
                            <i class="bi bi-people"></i>
                            العملاء
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link"
                                data-type="supplier"
                                type="button"
                                role="tab">
                            <i class="bi bi-truck"></i>
                            الموردين
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link"
                                data-type="other"
                                type="button"
                                role="tab">
                            <i class="bi bi-journal-text"></i>
                            الحسابات
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link"
                                data-type="bank"
                                type="button"
                                role="tab">
                            <i class="bi bi-bank"></i>
                            البنوك
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link"
                                data-type="cash"
                                type="button"
                                role="tab">
                            <i class="bi bi-cash-stack"></i>
                            الصناديق
                        </button>
                    </li>
                </ul>

                <!-- حقل البحث + زر تفريغ -->
                <div class="row g-2 mb-3">
                    <div class="col-md-10">
                        <input type="text"
                               class="form-control"
                               id="AccountPickerSearch"
                               placeholder="ابحث بالكود أو الاسم..."
                               autocomplete="off"
                               autofocus>
                    </div>
                    <div class="col-md-2">
                        <button type="button"
                                class="btn btn-outline-secondary w-100"
                                id="AccountPickerClearBtn">
                            <i class="bi bi-x-lg"></i>
                            تفريغ
                        </button>
                    </div>
                </div>

                <!-- جدول النتائج -->
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-bordered table-hover align-middle mb-0">
                        <thead class="table-light sticky-top">
                            <tr class="text-center">
                                <th style="width: 18%;">الكود</th>
                                <th>الاسم</th>
                                <th style="width: 18%;" id="AccountPickerColExtra">معلومة إضافية</th>
                                <th style="width: 18%;" id="AccountPickerColBalance">الرصيد</th>
                            </tr>
                        </thead>
                        <tbody id="AccountPickerResults">
                            <tr><td colspan="4" class="text-center text-muted py-4">ابدأ البحث</td></tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>