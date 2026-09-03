<!-- =========================================================
     مودال اختيار العملة (عام)
     ========================================================= -->

<div
    class="modal fade"
    id="CurrencyModal"
    tabindex="-1"
    aria-labelledby="CurrencyModalLabel"
    aria-hidden="true"
>
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <!-- رأس المودال -->
            <div class="modal-header">
                <h5 class="modal-title" id="CurrencyModalLabel">
                    <i class="bi bi-currency-exchange me-2"></i>
                    اختيار العملة
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
                        id="CurrencySearchInput"
                        placeholder="ابحث عن العملة (اسم أو رمز)..."
                        oninput="Currency.search()"
                        autofocus
                    >
                </div>

                <!-- جدول النتائج -->
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr class="text-center">
                                <th>#</th>
                                <th>اسم العملة</th>
                                <th>الرمز</th>
                                <th>سعر الصرف</th>
                                <th>اختيار</th>
                            </tr>
                        </thead>
                        <tbody id="CurrencyResults">
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">
                                    ابحث عن العملة
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div><!-- /.modal-body -->

        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->