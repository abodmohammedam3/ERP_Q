<!-- =========================================================
     مودال اختيار المورد (عام)
     ========================================================= -->

<div
    class="modal fade"
    id="SupplierModal"
    tabindex="-1"
    aria-labelledby="SupplierModalLabel"
    aria-hidden="true"
>
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <!-- رأس المودال -->
            <div class="modal-header">
                <h5 class="modal-title" id="SupplierModalLabel">
                    <i class="bi bi-person me-2"></i>
                    اختيار المورد
                </h5>
                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="إغلاق"
                ></button>
            </div>

            <!-- جسم المودال -->
            <div class="modal-body">

                <!-- حقل البحث (يعمل تلقائياً عند الكتابة) -->
                <div class="mb-3">
                    <input
                        type="text"
                        class="form-control"
                        id="SupplierSearchInput"
                        placeholder="ابحث عن المورد (اسم أو رقم محاسبي)..."
                        oninput="Supplier.search()"
                        autofocus
                    >
                </div>

                <!-- جدول النتائج -->
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr class="text-center">
                                <th>#</th>
                                <th>اسم المورد</th>
                                <th>الرقم المحاسبي</th>
                                <th>اختيار</th>
                            </tr>
                        </thead>
                        <tbody id="SupplierResults">
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">
                                    ابحث عن المورد
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div><!-- /.modal-body -->

        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->