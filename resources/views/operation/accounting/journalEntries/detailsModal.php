<div class="modal fade" id="jeDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"
         style="max-width: 1100px;">
        <div class="modal-content" style="height: 85vh;">

            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-journal-text"></i>
                    تفاصيل القيد
                    <span id="jeDetailsNo" class="text-primary"></span>
                </h5>
                <button type="button" class="btn-close"
                        data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body d-flex flex-column overflow-hidden">

                {{-- رأس القيد --}}
                <div class="row mb-3">
                    <div class="col-md-3">
                        <small class="text-muted d-block">التاريخ</small>
                        <strong id="jeDetailsDate">—</strong>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">نوع المستند</small>
                        <span id="jeDetailsDocType" class="badge bg-secondary">—</span>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">رقم المستند</small>
                        <strong id="jeDetailsDocNo">—</strong>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">الإجمالي</small>
                        <strong id="jeDetailsTotal" class="text-primary">—</strong>
                    </div>
                </div>

                {{-- البيان --}}
                <div class="alert alert-light border mb-3">
                    <small class="text-muted d-block mb-1">البيان</small>
                    <span id="jeDetailsDesc">—</span>
                </div>

                {{-- الجدول --}}
                <div class="table-responsive flex-grow-1 overflow-auto border rounded">
                    <table class="table table-bordered table-sm mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th width="40">#</th>
                                <th width="100">رقم الحساب</th>
                                <th>اسم الحساب</th>
                                <th width="70">العملة</th>
                                <th width="80">السعر</th>
                                <th width="110">مدين</th>
                                <th width="110">دائن</th>
                                <th width="120">مدين (محلي)</th>
                                <th width="120">دائن (محلي)</th>
                                <th>ملاحظات</th>
                            </tr>
                        </thead>
                        <tbody id="jeDetailsBody"></tbody>
                        <tfoot id="jeDetailsFooter"></tfoot>
                    </table>
                </div>

            </div>

            <div class="modal-footer">
                <button type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal">
                    إغلاق
                </button>
                <button type="button"
                        class="btn btn-primary"
                        id="jeBtnPrintDetails">
                    <i class="bi bi-printer"></i> طباعة القيد
                </button>
            </div>

        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════ --}}
    {{--  قالب صف تفاصيل                                      --}}
    {{-- ═══════════════════════════════════════════════════════ --}}
    <template id="jeDetailsRowTemplate">
        <tr>
            <td class="je-line-index"></td>
            <td class="je-line-code"></td>
            <td class="je-line-name"></td>
            <td class="je-line-currency"></td>
            <td class="je-line-rate"></td>
            <td class="je-line-debit text-end"></td>
            <td class="je-line-credit text-end"></td>
            <td class="je-line-local-debit text-end fw-bold"></td>
            <td class="je-line-local-credit text-end fw-bold"></td>
            <td class="je-line-notes"></td>
        </tr>
    </template>

    {{-- ═══════════════════════════════════════════════════════ --}}
    {{--  قالب صف الإجماليات                                  --}}
    {{-- ═══════════════════════════════════════════════════════ --}}
    <template id="jeDetailsFooterTemplate">
        <tr>
            <td colspan="5" class="text-end fw-bold">الإجماليات:</td>
            <td class="text-end fw-bold je-foot-debit"></td>
            <td class="text-end fw-bold je-foot-credit"></td>
            <td class="text-end fw-bold je-foot-local-debit"></td>
            <td class="text-end fw-bold je-foot-local-credit"></td>
            <td></td>
        </tr>
    </template>

</div>