<div class="card shadow-sm mb-3">

    <div class="card-header">

        <h6 class="mb-0">

            <i class="bi bi-file-earmark-text"></i>

            بيانات الحركة

        </h6>

    </div>


    <div class="card-body">

        <div class="row g-3">


            {{-- =================================================
                 رقم الحركة المعروض للمستخدم
                 ================================================= --}}
            <div class="col-md-2">

                <label
                    for="movementDisplayId"
                    class="form-label"
                >
                    رقم الحركة
                </label>

                <input
                    type="text"
                    id="movementDisplayId"
                    class="form-control"
                    readonly
                >

            </div>


            {{-- =================================================
                 نوع الحركة
                 ================================================= --}}
            <div class="col-md-2">

                <label
                    for="movementType"
                    class="form-label"
                >
                    نوع الحركة
                </label>

                <input
                    type="text"
                    id="movementType"
                    class="form-control"
                    readonly
                >

            </div>


            {{-- =================================================
                 اتجاه الحركة
                 ================================================= --}}
            <div class="col-md-2">

                <label
                    for="movementDirection"
                    class="form-label"
                >
                    اتجاه الحركة
                </label>

                <input
                    type="text"
                    id="movementDirection"
                    class="form-control"
                    readonly
                >

            </div>


            {{-- =================================================
                 التاريخ
                 ================================================= --}}
            <div class="col-md-2">

                <label
                    for="movementDate"
                    class="form-label"
                >
                    التاريخ
                </label>

                <input
                    type="date"
                    id="movementDate"
                    class="form-control"
                    readonly
                >

            </div>


            {{-- =================================================
                 رقم المستند
                 ================================================= --}}
            <div class="col-md-2">

                <label
                    for="movementDocumentNumber"
                    class="form-label"
                >
                    رقم المستند
                </label>

                <input
                    type="text"
                    id="movementDocumentNumber"
                    class="form-control"
                    readonly
                >

            </div>


            {{-- =================================================
                 المخزن
                 ================================================= --}}
            <div class="col-md-2">

                <label
                    for="movementWarehouse"
                    class="form-label"
                >
                    المخزن
                </label>

                <input
                    type="text"
                    id="movementWarehouse"
                    class="form-control"
                    readonly
                >

                <input
                    type="hidden"
                    id="movementWarehouseId"
                >

            </div>


            {{-- =================================================
                 البيان
                 ================================================= --}}
            <div class="col-12">

                <label
                    for="movementStatement"
                    class="form-label"
                >
                    البيان
                </label>

                <textarea
                    id="movementStatement"
                    class="form-control"
                    rows="2"
                    readonly
                ></textarea>

            </div>

        </div>

    </div>

</div>