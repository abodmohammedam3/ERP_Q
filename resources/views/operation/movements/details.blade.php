<div class="card mb-3">

    <div class="card-header d-flex justify-content-between align-items-center">

        <strong>
            <i class="bi bi-box-seam"></i>
            تفاصيل الحركة
        </strong>

                <button
                    type="button"
                    id="btnAddMovementRow"
                    class="btn btn-sm btn-success"
                    onclick="addMovementRow()"
                    disabled
                >
                    <i class="bi bi-plus-lg"></i>
                    إضافة صنف
                </button>

    </div>

    <div class="card-body p-0">

        <div class="table-responsive">

            <table class="table table-bordered table-hover align-middle text-center mb-0 movement-table">

                <thead class="table-light">
                    <tr>
                        <th style="min-width: 50px;">الرقم</th>
                        <th style="min-width: 140px;">الصنف</th>
                        <th style="min-width: 110px;">النوع</th>
                        <th style="min-width: 80px;">الرمز</th>
                        <th style="min-width: 130px;">المخزن</th>
                        <th style="min-width: 90px;">الوحدة</th>
                        <th style="min-width: 90px;">الكمية</th>
                        <th style="min-width: 115px;">سعر تكلفة الوحدة</th>
                        <th style="min-width: 100px;">الحد الأدنى</th>
                        <th style="min-width: 100px;">الحد الأعلى</th>
                        <th style="min-width: 110px;">سعر البيع للحبة</th>
                        <th style="min-width: 120px;">الإجمالي</th>
                    </tr>
                </thead>

                <tbody id="movementDetails"></tbody>

                <tfoot>
                    <tr>
                        <td colspan="12" class="p-3 bg-light">
                            <div class="d-flex justify-content-end">
                                <div class="d-flex align-items-center gap-2 border rounded-3 bg-white p-2 px-3">
                                    <span class="text-success small fw-bold">إجمالي الحركة</span>
                                    <input
                                        type="text"
                                        id="movementTotal"
                                        class="form-control form-control-sm text-center fw-bold bg-white"
                                        style="width: 120px;"
                                        value="0.00"
                                        readonly
                                    >
                                </div>
                            </div>
                        </td>
                    </tr>
                </tfoot>

            </table>

        </div>

    </div>

</div>


{{-- ============================================================= --}}
{{-- قالب صف تفاصيل الحركة --}}
{{-- ============================================================= --}}
<template id="movementRowTemplate">
    <tr class="movement-detail-row">

        <td class="movement-row-number"></td>

        <td>
            <input type="hidden" class="movement-item-id">
            <input
                type="text"
                class="form-control form-control-sm movement-item"
                placeholder="اكتب أو اختر الصنف"
                autocomplete="off"
                onkeydown="movementItemKeyDown(event)"
            >
        </td>

        <td>
            <input type="hidden" class="movement-type-id">
            <input
                type="text"
                class="form-control form-control-sm movement-type"
                placeholder="اكتب أو اختر النوع"
                autocomplete="off"
                onkeydown="movementTypeKeyDown(event)"
            >
        </td>

        <td>
            <input
                type="text"
                class="form-control form-control-sm movement-code"
                placeholder="الرمز"
            >
        </td>

        <td>
            <input type="hidden" class="movement-warehouse-id">
            <input
                type="text"
                class="form-control form-control-sm movement-warehouse"
                placeholder="اكتب أو اختر المخزن"
                autocomplete="off"
                onkeydown="movementWarehouseRowKeyDown(event)"
            >
        </td>

        <td>
            <input type="hidden" class="movement-unit-id">
            <input
                type="text"
                class="form-control form-control-sm movement-unit"
                placeholder="اكتب أو اختر الوحدة"
                autocomplete="off"
                onkeydown="movementUnitKeyDown(event)"
            >
        </td>

        <td>
            <input
                type="number"
                class="form-control form-control-sm movement-quantity text-center"
                min="0"
                step="0.01"
                value="0.00"
                oninput="calculateMovementRow(this)"
            >
        </td>

        <td>
            <input
                type="number"
                class="form-control form-control-sm movement-unit-cost text-center"
                min="0"
                step="0.01"
                value="0.00"
                oninput="calculateMovementRow(this)"
            >
        </td>

        <td>
            <input
                type="number"
                class="form-control form-control-sm movement-min-price text-center"
                min="0"
                step="0.01"
                placeholder="0.00"
                oninput="validateSalePrice(this.closest('tr'))"
            >
        </td>

        <td>
            <input
                type="number"
                class="form-control form-control-sm movement-max-price text-center"
                min="0"
                step="0.01"
                placeholder="0.01"
                oninput="validateSalePrice(this.closest('tr'))"
            >
        </td>

        <td>
            <input
                type="number"
                class="form-control form-control-sm movement-sale-price text-center"
                min="0"
                step="0.01"
                value="0.0"
                oninput="validateSalePrice(this.closest('tr'))"
            >
        </td>

        <td>
            <input
                type="text"
                class="form-control form-control-sm movement-total text-center fw-bold bg-light"
                value="0.00"
                readonly
            >
        </td>

    </tr>
</template>


{{-- أزرار الحفظ --}}
<div id="movementSaveActions" class="d-flex justify-content-end gap-2 mt-3 flex-wrap d-none">
    <button
        type="button"
        id="btnSaveMovement"
        class="btn btn-success"
        onclick="saveMovement()"
    >
        <i class="bi bi-save"></i>
        حفظ
    </button>
    <button
        type="button"
        id="btnCancelMovement"
        class="btn btn-secondary"
        onclick="cancelMovement()"
    >
        <i class="bi bi-x-lg"></i>
        إلغاء
    </button>
</div>