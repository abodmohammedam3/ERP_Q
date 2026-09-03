<div
    class="modal fade"
    id="salesItemModal"
    tabindex="-1"
    aria-labelledby="salesItemModalLabel"
    aria-hidden="true"
>

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header">

                <h5
                    class="modal-title"
                    id="salesItemModalLabel"
                >
                    <i class="bi bi-box-seam"></i>
                    اختيار الصنف
                </h5>

                <button
                    type="button"
                    class="btn-close"
                    style="margin-right: auto; margin-left: 0;"
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
                            id="salesItemSearchInput"
                            placeholder="اسم الصنف"
                        >

                    </div>

                    <div class="col-md-2">

                        <button
                            type="button"
                            class="btn btn-primary w-100"
                            onclick="searchSalesItems()"
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
                                <th>الصنف</th>
                                <th>الرمز</th>
                                <th>اختيار</th>

                            </tr>

                        </thead>

                        <tbody id="salesItemResults"></tbody>

                    </table>

                </div>

            </div>

        </div>

    </div>

</div>