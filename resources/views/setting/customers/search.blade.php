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
                data-bs-target="#CustomerFilters"
                aria-expanded="true"
                aria-controls="CustomerFilters">
                <i class="bi bi-sliders me-1"></i>
                الفلاتر
            </button>
        </div>
    </div>

    {{-- محتوى البحث --}}
    <div class="collapse show" id="CustomerFilters">
        <div class="card-body">
            <div class="row g-3">

                {{-- اسم العميل --}}
                <div class="col-12 col-md-6 col-xl-4">
                    <label
                        for="searchName"
                        class="form-label">
                        اسم العميل
                    </label>
                    <div class="input-group">
                        <input
                            type="text"
                            class="form-control"
                            id="searchName"
                            name="search_name"
                            placeholder="ابحث باسم العميل..."
                            autocomplete="off">
                    </div>
                </div>

                {{-- رقم الهاتف --}}
                <div class="col-12 col-md-6 col-xl-4">
                    <label
                        for="searchPhone"
                        class="form-label">
                        رقم الهاتف
                    </label>
                    <div class="input-group">
                        <input
                            type="text"
                            class="form-control"
                            id="searchPhone"
                            name="search_phone"
                            placeholder="ابحث برقم الهاتف..."
                            autocomplete="off">
                    </div>
                </div>

                {{-- رقم الحساب المحاسبي --}}
                <div class="col-12 col-md-6 col-xl-4">
                    <label
                        for="searchCode"
                        class="form-label">
                        رقم الحساب
                    </label>
                    <div class="input-group">
                        <input
                            type="text"
                            class="form-control"
                            id="searchCode"
                            name="search_code"
                            placeholder="رقم الحساب المرتبط..."
                            autocomplete="off">
                    </div>
                </div>

            </div>
        </div>
    </div>

</div>