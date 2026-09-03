<!-- =========================================================
     مودال اختيار العميل (عام)
     ========================================================= -->

<div
    class="modal fade"
    id="CustomerModal"
    tabindex="-1"
    aria-labelledby="CustomerModalLabel"
    aria-hidden="true"
>
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <!-- رأس المودال -->
            <div class="modal-header">
                <h5 class="modal-title" id="CustomerModalLabel">
                    <i class="bi bi-person me-2"></i>
                    اختيار العميل
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
                        id="CustomerSearchInput"
                        placeholder="ابحث عن العميل (اسم أو رقم محاسبي)..."
                        oninput="Customer.search()"
                        autofocus
                    >
                </div>

                <!-- جدول النتائج -->
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr class="text-center">
                                <th>#</th>
                                <th>اسم العميل</th>
                                <th>الرقم المحاسبي</th>
                                <th>اختيار</th>
                            </tr>
                        </thead>
                        <tbody id="CustomerResults">
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">
                                    ابحث عن العميل
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div><!-- /.modal-body -->

        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->