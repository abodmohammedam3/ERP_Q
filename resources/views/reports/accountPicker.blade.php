{{-- ═══════════════════════════════════════════════════════════
     مودال اختيار الحساب من دليل الحسابات — خاص بمركز التقارير
     الشكل مطابق لمودال الرصيد الافتتاحي (بحث + جدول)
     مستقل تماماً عن النظام الموحّد shared/lookup — لا يمسّه ولا يُمسّ به
     ═══════════════════════════════════════════════════════════ --}}

<div class="modal fade" id="rcAccountPickerModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"
         style="max-width: 900px;">
        <div class="modal-content" style="height: 75vh;">

            {{-- الرأس --}}
            <div class="modal-header position-relative">
                <h5 class="modal-title" id="rcPickerTitle">
                    <i class="bi bi-journal-bookmark text-primary me-2"></i>
                    اختيار حساب من دليل الحسابات
                </h5>
                <button type="button"
                        class="btn-close position-absolute top-0 end-0 m-3"
                        data-bs-dismiss="modal">
                </button>
            </div>

            {{-- الجسم --}}
            <div class="modal-body d-flex flex-column overflow-hidden">

                {{-- شريط البحث (بحث محلي — بلا شبكة) --}}
                <div class="input-group mb-3">
                    <span class="input-group-text">
                        <i class="bi bi-search"></i>
                    </span>

                    <input type="text"
                           id="rcPickerSearch"
                           class="form-control"
                           placeholder="ابحث بالاسم أو برقم الحساب..."
                           autocomplete="off">

                    <button type="button"
                            class="btn btn-outline-secondary"
                            id="rcPickerClear"
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
                            </tr>
                        </thead>
                        <tbody id="rcPickerBody"></tbody>
                    </table>
                </div>

                {{-- لا توجد نتائج --}}
                <div class="text-center text-muted py-3"
                     id="rcPickerEmpty"
                     style="display: none;">
                    لا توجد نتائج
                </div>

            </div>

        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════ --}}
    {{--  قوالب الصفوف                                       --}}
    {{-- ═══════════════════════════════════════════════════════ --}}

    <template id="rcPickerRowTemplate">
        <tr style="cursor: pointer;">
            <td class="rc-picker-code"></td>
            <td class="rc-picker-name"></td>
        </tr>
    </template>

    <template id="rcPickerLoadingTemplate">
        <tr>
            <td colspan="2" class="text-center text-muted py-3">
                <span class="spinner-border spinner-border-sm me-2"></span>
                جارٍ التحميل...
            </td>
        </tr>
    </template>

</div>