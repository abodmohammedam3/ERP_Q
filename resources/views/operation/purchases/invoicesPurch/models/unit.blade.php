<div
    class="modal fade"
    id="salesUnitModal"
    tabindex="-1"
    aria-labelledby="salesUnitModalLabel"
    aria-hidden="true"
>

    <div class="modal-dialog modal-md modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header">

                <h5
                    class="modal-title"
                    id="salesUnitModalLabel"
                >
                    <i class="bi bi-rulers"></i>
                    اختيار الوحدة
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

                <div class="row g-2">

                    <div class="col-12">

                        <button
                            type="button"
                            class="btn btn-outline-primary w-100"
                            onclick="selectSalesUnit('كيلو')"
                        >
                            كيلو
                        </button>

                    </div>

                    <div class="col-12">

                        <button
                            type="button"
                            class="btn btn-outline-primary w-100"
                            onclick="selectSalesUnit('حبه')"
                        >
                            حبه
                        </button>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>