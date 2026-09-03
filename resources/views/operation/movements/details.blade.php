<div class="card shadow-sm mb-3">


    {{-- =========================================================
         رأس تفاصيل الحركة
         ========================================================= --}}
    <div class="card-header">

        <div class="d-flex justify-content-between align-items-center">

            <h6 class="mb-0">

                <i class="bi bi-box-seam"></i>

                تفاصيل الحركة

            </h6>


            {{-- إضافة صنف تظهر وتعمل في وضع add فقط --}}

            <button
                type="button"
                id="btnAddMovementRow"
                class="btn btn-sm btn-primary"
                onclick="addMovementRow()"
                disabled
            >

                <i class="bi bi-plus-lg"></i>

                إضافة صنف

            </button>

        </div>

    </div>


    <div class="card-body">


        <div class="table-responsive">

            <table
                class="table table-bordered table-hover align-middle text-center mb-0"
            >


                {{-- =================================================
                     عناوين الأعمدة
                     ================================================= --}}
                <thead class="table-light">

                    <tr>

                        <th>
                            الرقم
                        </th>

                        <th>
                            الصنف
                        </th>

                        <th>
                            النوع
                        </th>

                        <th>
                            الرمز
                        </th>

                        <th>
                            المخزن
                        </th>

                        <th>
                            الوحدة
                        </th>

                        <th>
                            الكمية
                        </th>

                        <th>
                            سعر تكلفة الوحدة
                        </th>

                        <th>
                            سعر البيع للحبة
                        </th>

                        <th>
                            الإجمالي
                        </th>

                    </tr>

                </thead>


                {{-- =================================================
                     تفاصيل الحركة
                     ================================================= --}}
                <tbody id="movementDetails">

                    {{--

                        الصفوف ستتم إضافتها بواسطة
                        inventory_movements.js

                    --}}

                </tbody>


                {{-- =================================================
                     إجمالي الحركة
                     ================================================= --}}
                <tfoot>

                    <tr>

                        <th
                            colspan="9"
                            class="text-end"
                        >
                            إجمالي الحركة
                        </th>

                        <th id="movementTotal">
                            0.00
                        </th>

                    </tr>

                </tfoot>

            </table>

        </div>

    </div>

</div>


{{-- =============================================================
     أزرار الحفظ
     تظهر فقط أثناء إضافة أمر توريد / صرف
     ============================================================= --}}
<div
    id="movementSaveActions"
    class="card shadow-sm mb-3 d-none"
>

    <div class="card-body">

        <div class="d-flex flex-wrap gap-2">


            {{-- حفظ --}}
            <button
                type="button"
                id="btnSaveMovement"
                class="btn btn-success"
                onclick="saveMovement()"
            >

                <i class="bi bi-check-lg"></i>

                حفظ

            </button>


            {{-- إلغاء الإضافة --}}
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

    </div>

</div>