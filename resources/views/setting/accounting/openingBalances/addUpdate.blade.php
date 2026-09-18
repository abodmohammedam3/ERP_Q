<div class="modal fade" id="addEditModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"
         style="max-width: 1200px;">
        <div class="modal-content" style="height: 85vh;">

            {{-- الرأس --}}
            <div class="modal-header position-relative">
                <h5 class="modal-title" id="addEditTitle">
                    إضافة رصيد افتتاحي
                </h5>
                <button type="button"
                        class="btn-close position-absolute top-0 start-0 m-3"
                        data-bs-dismiss="modal">
                </button>
            </div>

            {{-- النموذج --}}
            <form id="obForm" class="d-flex flex-column flex-grow-1 overflow-hidden">
                @csrf
                <input type="hidden" id="editId" name="id">

                <div class="modal-body d-flex flex-column overflow-hidden">

                    {{-- زر إضافة سطر --}}
                    <div class="row mb-3 align-items-end">
                        <div class="col-12 text-end">
                            <button type="button"
                                    class="btn btn-outline-primary"
                                    id="btnAddLine">
                                <i class="bi bi-plus-lg"></i>
                                إضافة سطر
                            </button>
                        </div>
                    </div>

                    {{-- جدول الأسطر --}}
                    <div class="table-responsive flex-grow-1 overflow-auto border rounded">
                        <table class="table table-bordered mb-0" id="linesTable">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th width="40">الرقم</th>
                                    <th width="110">النوع</th>
                                    <th width="200">الحساب</th>
                                    <th width="120">العملة</th>
                                    <th width="100">سعر الصرف</th>
                                    <th width="120">مدين</th>
                                    <th width="120">دائن</th>
                                    <th>ملاحظات</th>
                                    <th width="60"></th>
                                </tr>
                            </thead>
                            <tbody id="linesBody"></tbody>
                        </table>
                    </div>

                </div>

                {{-- الأزرار --}}
                <div class="modal-footer">
                    <button type="button"
                            class="btn btn-secondary"
                            data-bs-dismiss="modal">
                        إلغاء
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i>
                        حفظ
                    </button>
                </div>
            </form>

        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════ --}}
    {{--  قالب السطر (يُستخدم من الجافاسكربت)                    --}}
    {{-- ═══════════════════════════════════════════════════════ --}}
    <template id="lineRowTemplate">
        <tr>

            <td class="line-number"></td>

            {{-- النوع --}}
            <td>
                <select class="form-select form-select-sm line-type" required>
                    <option value="CASH">صندوق</option>
                    <option value="BANK">بنك</option>
                    <option value="CUSTOMER">عميل</option>
                    <option value="SUPPLIER">مورد</option>
                </select>
            </td>

            {{-- الحساب --}}
            <td>
                <input type="text"
                       class="form-control form-control-sm line-account-display"
                       placeholder="اضغط للاختيار..."
                       readonly
                       style="cursor: pointer;">

                <input type="hidden" class="line-account-id">
                <input type="hidden" class="line-account-code">
                <input type="hidden" class="line-entity-id">
            </td>

            {{-- العملة --}}
            <td>
                <select class="form-select form-select-sm line-currency">
                    @isset($currencies)
                        @foreach($currencies as $currency)
                            <option value="{{ $currency->coinsID }}"
                                    data-rate="{{ $currency->coinsExchangeRate ?? 1 }}">
                                {{ $currency->coinsName }}
                            </option>
                        @endforeach
                    @endisset
                </select>
            </td>

            {{-- سعر الصرف --}}
            <td>
                <input type="number"
                       step="0.01"
                       min="0.01"
                       class="form-control form-control-sm line-rate"
                       value="1.00">
            </td>

            {{-- مدين --}}
            <td>
                <input type="number"
                       step="0.01"
                       min="0"
                       class="form-control form-control-sm line-debit"
                       value="0">
            </td>

            {{-- دائن --}}
            <td>
                <input type="number"
                       step="0.01"
                       min="0"
                       class="form-control form-control-sm line-credit"
                       value="0">
            </td>

            {{-- ملاحظات --}}
            <td>
                <input type="text"
                       class="form-control form-control-sm line-notes">
            </td>

            {{-- حذف --}}
            <td>
                <button type="button"
                        class="btn btn-sm btn-danger btn-remove-line"
                        title="حذف السطر">
                    <i class="bi bi-x-lg"></i>
                </button>
            </td>

        </tr>
    </template>

</div>