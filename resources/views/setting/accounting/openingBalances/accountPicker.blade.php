{{-- ═══════════════════════════════════════════════════════════
     مودل اختيار الكيان (صندوق / بنك / عميل / مورد)
     ═══════════════════════════════════════════════════════════ --}}

<div class="modal fade" id="accountPickerModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"
         style="max-width: 800px;">
        <div class="modal-content" style="height: 70vh;">

            {{-- الرأس --}}
            <div class="modal-header">
                <h5 class="modal-title" id="accountPickerTitle">
                    اختيار
                </h5>
                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                </button>
            </div>

            {{-- الجسم --}}
            <div class="modal-body d-flex flex-column overflow-hidden">

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

                {{-- جدول الكيانات --}}
                <div class="table-responsive flex-grow-1 overflow-auto border rounded">
                    <table class="table table-hover mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th width="140" id="accountPickerCodeHeader">
                                    الرمز
                                </th>
                                <th>الاسم</th>
                                <th width="120" id="accountPickerAccountHeader">
                                    الحساب
                                </th>
                                <th width="100"
                                    id="accountPickerCurrencyHeader"
                                    class="picker-currency-col">
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
    {{--  قالب صف الكيان                                        --}}
    {{-- ═══════════════════════════════════════════════════════ --}}
    <template id="accountPickerRowTemplate">
        <tr style="cursor: pointer;">
            <td class="picker-code"></td>
            <td class="picker-name"></td>
            <td class="picker-account"></td>
            <td class="picker-currency picker-currency-col"></td>
        </tr>
    </template>

</div>