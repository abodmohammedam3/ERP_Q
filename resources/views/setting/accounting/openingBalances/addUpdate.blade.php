<div class="modal fade" id="addEditModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"
         style="max-width: 1100px;">
        <div class="modal-content" style="height: 85vh;">

            <div class="modal-header position-relative">
                <h5 class="modal-title" id="addEditTitle">إضافة رصيد افتتاحي</h5>
                <button type="button" class="btn-close position-absolute top-0 start-0 m-3" data-bs-dismiss="modal"></button>
            </div>

            <form id="obForm" class="d-flex flex-column flex-grow-1 overflow-hidden">
                @csrf
                <input type="hidden" id="editId" name="id">

                {{-- جسم المودل: ثابت مع تمرير داخلي --}}
                <div class="modal-body d-flex flex-column overflow-hidden">

                    {{-- الصف العلوي: النوع + زر إضافة سطر --}}
                    <div class="row mb-3 align-items-end ">
                        <div class="col-md-4">
                            <label class="form-label">النوع</label>
                            <select id="formType" name="type" class="form-select" required>
                                <option value="CASH">صندوق</option>
                                <option value="BANK">بنك</option>
                                <option value="CUSTOMER">عميل</option>
                                <option value="SUPPLIER">مورد</option>
                            </select>
                        </div>

                        <div class="col-md-8 text-end">
                            <button type="button"
                                    class="btn btn-outline-primary"
                                    id="btnAddLine">
                                <i class="fas fa-plus"></i> إضافة سطر
                            </button>
                        </div>
                    </div>

                    {{-- جدول الأسطر: قابل للتمرير --}}
                    <div class="table-responsive flex-grow-1 overflow-auto border rounded">
                        <table class="table table-bordered mb-0" id="linesTable">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th width="40">#</th>
                                    <th width="110">رقم الحساب</th>
                                    <th>اسم الحساب</th>
                                    <th width="120">العملة</th>
                                    <th width="110">سعر الصرف</th>
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

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        إلغاء
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> حفظ
                    </button>
                </div>
            </form>

        </div>
    </div>

    {{-- قالب السطر --}}
    <template id="lineRowTemplate">
        <tr>
            <td class="line-number"></td>

            <td>
                <input type="text"
                       class="form-control form-control-sm line-account-code"
                       readonly>
            </td>

            <td>
                <select class="form-select form-select-sm line-account" required>
                    <option value="">اختر الحساب</option>
                    @isset($accounts)
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}"
                                    data-code="{{ $account->code }}">
                                {{ $account->name }}
                            </option>
                        @endforeach
                    @endisset
                </select>
            </td>

            <td>
                <select class="form-select form-select-sm line-currency" required>
                    @isset($currencies)
                        @foreach($currencies as $currency)
                            <option value="{{ $currency->id }}"
                                    data-rate="{{ $currency->exchange_rate ?? 1 }}">
                                {{ $currency->code }}
                            </option>
                        @endforeach
                    @endisset
                </select>
            </td>

            <td>
                <input type="number"
                       step="0.000001"
                       class="form-control form-control-sm line-rate"
                       value="1">
            </td>

            <td>
                <input type="number"
                       step="0.01"
                       class="form-control form-control-sm line-debit"
                       value="0">
            </td>

            <td>
                <input type="number"
                       step="0.01"
                       class="form-control form-control-sm line-credit"
                       value="0">
            </td>

            <td>
                <input type="text"
                       class="form-control form-control-sm line-notes">
            </td>

            <td>
                <button type="button"
                        class="btn btn-sm btn-danger btn-remove-line">
                    <i class="fas fa-times"></i>
                </button>
            </td>
        </tr>
    </template>

</div>