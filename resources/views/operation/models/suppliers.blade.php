<!-- ===================================================== --><!-- نافذة اختيار المورد --><!-- ===================================================== -->
<div
    class="modal fade"
    id="supplierModal"
    tabindex="-1"
    aria-labelledby="supplierModalLabel"
    aria-hidden="true"
><div class="modal-dialog modal-lg modal-dialog-centered">

    <div class="modal-content">

        <div class="modal-header">

            <h5
                class="modal-title"
                id="supplierModalLabel"
            >
                <i class="bi bi-person"></i>
                اختيار المورد
            </h5>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="modal"
                aria-label="إغلاق"
            ></button>

        </div>

        <div class="modal-body">

            <div class="row g-2 mb-3">

                <div class="col-md-10">

                    <input
                        type="text"
                        class="form-control"
                        id="supplierSearchInput"
                        placeholder="اسم المورد أو الرقم المحاسبي"
                    >

                </div>

                <div class="col-md-2">

                    <button
                        type="button"
                        class="btn btn-primary w-100"
                        onclick="searchSuppliers()"
                    >
                        بحث
                    </button>

                </div>

            </div>


            <div class="table-responsive">

                <table class="table table-bordered table-hover">

                    <thead class="table-light">

                        <tr class="text-center">

                            <th>الرقم</th>
                            <th>اسم المورد</th>
                            <th>الرقم المحاسبي</th>
                            <th>اختيار</th>

                        </tr>

                    </thead>

                    <tbody id="supplierResults">

                        <tr>

                            <td
                                colspan="4"
                                class="text-center text-muted py-3"
                            >
                                لا توجد نتائج
                            </td>

                        </tr>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>
