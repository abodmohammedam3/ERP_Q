<div class="card border-0 shadow-sm mb-4">

    {{-- رأس البحث --}}
    <div class="card-header bg-body border-bottom">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">

            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-search text-primary"></i>

                <h6 class="mb-0 fw-bold">
                    البحث والتصفية
                </h6>
            </div>

            <button
                type="button"
                class="btn btn-sm btn-outline-secondary"
                data-bs-toggle="collapse"
                data-bs-target="#SupplierFilters"
                aria-expanded="true"
                aria-controls="SupplierFilters">

                <i class="bi bi-sliders me-1"></i>
                الفلاتر

            </button>

        </div>
    </div>


    {{-- محتوى البحث --}}
    <div class="collapse show" id="SupplierFilters">

        <div class="card-body">

            <div class="row g-3">

                {{-- اسم المورد --}}
                <div class="col-12 col-md-6 col-xl-4">

                    <label
                        for="searchSupplierName"
                        class="form-label">

                        اسم المورد

                    </label>

                    <div class="input-group">

                        <input
                            type="text"
                            class="form-control"
                            id="searchSupplierName"
                            name="search_name"
                            placeholder="ابحث باسم المورد..."
                            autocomplete="off">

                    </div>

                </div>


                {{-- رقم الهاتف --}}
                <div class="col-12 col-md-6 col-xl-4">

                    <label
                        for="searchSupplierPhone"
                        class="form-label">

                        رقم الهاتف

                    </label>

                    <div class="input-group">

                        <input
                            type="text"
                            class="form-control"
                            id="searchSupplierPhone"
                            name="search_phone"
                            placeholder="ابحث برقم الهاتف..."
                            autocomplete="off">

                    </div>

                </div>


                {{-- رقم الحساب التحليلي --}}
                <div class="col-12 col-md-6 col-xl-4">

                    <label
                        for="searchSupplierCode"
                        class="form-label">

                        رقم الحساب

                    </label>

                    <div class="input-group">

                        <input
                            type="text"
                            class="form-control"
                            id="searchSupplierCode"
                            name="search_code"
                            placeholder="رقم الحساب التحليلي..."
                            autocomplete="off">

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>