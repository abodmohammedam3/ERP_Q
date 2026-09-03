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
                {{-- نوع الحساب --}}
                <div class="col-12 col-md-6 col-xl-3">
                    <label
                        for="searchType"
                        class="form-label">
                        نوع الحساب
                    </label>
                    <select
                        class="form-select"
                        id="searchType"
                        name="search_type">
                        <option value="">
                            جميع الأنواع
                        </option>
                        <option value="assets">
                            أصول
                        </option>
                        <option value="liabilities">
                            خصوم
                        </option>
                        <option value="equity">
                            حقوق ملكية
                        </option>
                        <option value="revenue">
                            إيرادات
                        </option>
                        <option value="expense">
                            مصروفات
                        </option>
                    </select>
                </div>
                {{-- طبيعة الحساب --}}
                <div class="col-12 col-md-6 col-xl-3">
                    <label
                        for="searchNature"
                        class="form-label">
                        طبيعة الحساب
                    </label>
                    <select
                        class="form-select"
                        id="searchNature"
                        name="search_nature">
                        <option value="">
                            جميع الطبائع
                        </option>
                        <option value="debit">
                            مدين
                        </option>
                        <option value="credit">
                            دائن
                        </option>
                    </select>
                </div>
            </div>
        </div>
    </div>
</div>