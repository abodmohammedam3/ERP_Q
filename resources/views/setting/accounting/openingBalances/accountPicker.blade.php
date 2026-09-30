{{-- ═══════════════════════════════════════════════════════════
     مودال اختيار الحساب مع تبويبات
     ═══════════════════════════════════════════════════════════ --}}

<div class="modal fade" id="accountPickerModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"
         style="max-width: 900px;">
        <div class="modal-content" style="height: 75vh;">

            {{-- الرأس --}}
            <div class="modal-header position-relative">
                <h5 class="modal-title" id="accountPickerTitle">
                    اختيار حساب
                </h5>
                <button type="button"
                        class="btn-close position-absolute top-0 end-0 m-3"
                        data-bs-dismiss="modal">
                </button>
            </div>

            {{-- الجسم --}}
            <div class="modal-body d-flex flex-column overflow-hidden">

                {{-- التبويبات --}}
                <ul class="nav nav-tabs mb-3" id="pickerTabs" role="tablist">

                    <li class="nav-item" role="presentation">
                        <button class="nav-link active"
                                data-type="CUSTOMER"
                                type="button"
                                role="tab">
                            <i class="bi bi-people"></i>
                            العملاء
                        </button>
                    </li>

                    <li class="nav-item" role="presentation">
                        <button class="nav-link"
                                data-type="SUPPLIER"
                                type="button"
                                role="tab">
                            <i class="bi bi-truck"></i>
                            الموردين
                        </button>
                    </li>

                    <li class="nav-item" role="presentation">
                        <button class="nav-link"
                                data-type="INVENTORY"
                                type="button"
                                role="tab">
                            <i class="bi bi-box-seam"></i>
                            المخازن
                        </button>
                    </li>

                    <li class="nav-item" role="presentation">
                        <button class="nav-link"
                                data-type="BANK"
                                type="button"
                                role="tab">
                            <i class="bi bi-bank"></i>
                            البنوك
                        </button>
                    </li>

                    <li class="nav-item" role="presentation">
                        <button class="nav-link"
                                data-type="CASH"
                                type="button"
                                role="tab">
                            <i class="bi bi-cash-stack"></i>
                            الصناديق
                        </button>
                    </li>

                </ul>

                {{-- شريط البحث --}}
                <div class="input-group mb-3">
                    <span class="input-group-text">
                        <i class="bi bi-search"></i>
                    </span>

                    <input type="text"
                           id="accountPickerSearch"
                           class="form-control"
                           placeholder="ابحث بالاسم أو الرمز..."
                           autocomplete="off">

                    <button type="button"
                            class="btn btn-outline-secondary"
                            id="accountPickerClear"
                            title="مسح البحث">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                {{-- جدول الحسابات --}}
                <div class="table-responsive flex-grow-1 overflow-auto border rounded">
                    <table class="table table-hover mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th width="180">رقم الحساب</th>
                                <th>الاسم</th>
                                <th width="150" class="picker-phone-col">
                                    الهاتف
                                </th>
                                <th width="120" class="picker-currency-col">
                                    العملة
                                </th>
                            </tr>
                        </thead>
                        <tbody id="accountPickerBody"></tbody>
                    </table>
                </div>

                {{-- لا توجد نتائج --}}
                <div class="text-center text-muted py-3"
                     id="accountPickerEmpty"
                     style="display: none;">
                    لا توجد نتائج
                </div>

            </div>

        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════ --}}
    {{--  قوالب Picker                                        --}}
    {{-- ═══════════════════════════════════════════════════════ --}}

    <template id="accountPickerRowTemplate">
        <tr style="cursor: pointer;">
            <td class="picker-code"></td>
            <td class="picker-name"></td>
            <td class="picker-phone picker-phone-col"></td>
            <td class="picker-currency picker-currency-col"></td>
        </tr>
    </template>

    <template id="accountPickerLoadingTemplate">
        <tr>
            <td colspan="4" class="text-center text-muted py-3">
                جارٍ التحميل...
            </td>
        </tr>
    </template>

</div>