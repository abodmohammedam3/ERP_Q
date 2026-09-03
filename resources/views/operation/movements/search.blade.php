<div class="card shadow-sm mb-3">

    <div class="card-header">

        <h6 class="mb-0">
            <i class="bi bi-search"></i>
            البحث في حركات المخزون
        </h6>

    </div>


    <div class="card-body">

        <div class="row g-3 align-items-end">

            {{-- =================================================
                 المخزن
                 ================================================= --}}
            <div class="col-md-3">

                <label
                    for="searchWarehouse"
                    class="form-label"
                >
                    المخزن
                </label>

                <select
                    id="searchWarehouse"
                    class="form-select"
                >

                    <option value="">
                        جميع المخازن
                    </option>

                    <option value="1">
                        المخزن الرئيسي
                    </option>

                    <option value="2">
                        المخزن الثاني
                    </option>

                </select>

            </div>


            {{-- =================================================
                 نوع الحركة
                 ================================================= --}}
            <div class="col-md-3">

                <label
                    for="searchMovementType"
                    class="form-label"
                >
                    نوع الحركة
                </label>

                <select
                    id="searchMovementType"
                    class="form-select"
                >

                    <option value="">
                        جميع الحركات
                    </option>

                    <option value="supply">
                        توريد مخزني
                    </option>

                    <option value="issue">
                        صرف مخزني
                    </option>

                    <option value="purchase">
                        توريد شراء
                    </option>

                    <option value="sale">
                        صرف بيع
                    </option>

                    <option value="purchase_return">
                        مرتجع شراء
                    </option>

                    <option value="sale_return">
                        مرتجع بيع
                    </option>

                </select>

            </div>


            {{-- =================================================
                 من تاريخ
                 ================================================= --}}
            <div class="col-md-2">

                <label
                    for="searchDateFrom"
                    class="form-label"
                >
                    من تاريخ
                </label>

                <input
                    type="date"
                    id="searchDateFrom"
                    class="form-control"
                >

            </div>


            {{-- =================================================
                 إلى تاريخ
                 ================================================= --}}
            <div class="col-md-2">

                <label
                    for="searchDateTo"
                    class="form-label"
                >
                    إلى تاريخ
                </label>

                <input
                    type="date"
                    id="searchDateTo"
                    class="form-control"
                >

            </div>


            {{-- =================================================
                 زر البحث
                 ================================================= --}}
            <div class="col-md-2">

                <button
                    type="button"
                    id="btnSearchMovement"
                    class="btn btn-primary w-100"
                    onclick="searchMovements()"
                >

                    <i class="bi bi-search"></i>

                    بحث

                </button>

            </div>

        </div>

    </div>

</div>


{{-- =============================================================
     نتائج البحث
     لا توجد نافذة منبثقة.
     سيتم التحكم بها لاحقاً بواسطة JavaScript / Laravel.
     ============================================================= --}}

<div
    id="movementSearchResults"
    class="card shadow-sm mb-3 d-none"
>

    <div class="card-header">

        <h6 class="mb-0">

            <i class="bi bi-list-ul"></i>

            نتائج البحث

        </h6>

    </div>


    <div class="card-body">

        <div class="table-responsive">

            <table
                class="table table-bordered table-hover align-middle text-center mb-0"
            >

                <thead class="table-light">

                    <tr>

                        <th>
                            رقم الحركة
                        </th>

                        <th>
                            نوع الحركة
                        </th>

                        <th>
                            الاتجاه
                        </th>

                        <th>
                            التاريخ
                        </th>

                        <th>
                            رقم المستند
                        </th>

                        <th>
                            المخزن
                        </th>

                    </tr>

                </thead>


                <tbody id="movementSearchResultsBody">

                    {{-- نتائج البحث ستظهر هنا --}}

                </tbody>

            </table>

        </div>

    </div>

</div>