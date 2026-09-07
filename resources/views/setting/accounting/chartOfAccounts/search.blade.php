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
                data-bs-target="#accountSearchFilters"
                aria-expanded="true"
                aria-controls="accountSearchFilters">
                <i class="bi bi-sliders me-1"></i>
                الفلاتر
            </button>
        </div>
    </div>
    {{-- محتوى البحث --}}
    <div class="collapse show" id="accountSearchFilters">
        <div class="card-body">
            <div class="row g-3">
                {{-- رقم الحساب --}}
                <div class="col-12 col-md-6 col-xl-3">
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
                            placeholder="مثال: 1100">
                    </div>
                </div>
               {{-- اسم الحساب --}}
                <div class="col-12 col-md-6 col-xl-3">
                    <label
                        for="searchName"
                        class="form-label">
                        اسم الحساب
                    </label>

                    <div class="input-group">
                        <input
                            type="text"
                            class="form-control"
                            id="searchName"
                            name="search_name"
                            placeholder="مثال: الصندوق">
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>