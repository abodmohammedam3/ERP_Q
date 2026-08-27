{{-- فلاتر البحث --}}
<div class="card mb-3">
    <div class="card-header">
        <i class="bi bi-funnel"></i> خيارات البحث
    </div>
    <div class="card-body">
        <div class="row g-3">

            {{-- المخزن --}}
            <div class="col-md-3">
                <label class="form-label" for="filterStock">المخزن</label>
                <select class="form-select" id="filterStock" onchange="filterMovements()">
                    <option value="">جميع المخازن</option>
                    <option value="المعرض الرئيسي">المعرض الرئيسي</option>
                    <option value="مستودع الجملة">مستودع الجملة</option>
                </select>
            </div>

            {{-- نوع الحركة --}}
            <div class="col-md-3">
                <label class="form-label" for="filterType">نوع الحركة</label>
                <select class="form-select" id="filterType" onchange="filterMovements()">
                    <option value="">جميع الحركات</option>
                    <option value="شراء">شراء</option>
                    <option value="بيع">بيع</option>
                    <option value="مرتجع شراء">مرتجع شراء</option>
                    <option value="مرتجع بيع">مرتجع بيع</option>
                </select>
            </div>

            {{-- من تاريخ --}}
            <div class="col-md-2">
                <label class="form-label" for="filterDateFrom">من تاريخ</label>
                <input type="date" class="form-control" id="filterDateFrom" onchange="filterMovements()">
            </div>

            {{-- إلى تاريخ --}}
            <div class="col-md-2">
                <label class="form-label" for="filterDateTo">إلى تاريخ</label>
                <input type="date" class="form-control" id="filterDateTo" onchange="filterMovements()">
            </div>

            {{-- البحث --}}
            <div class="col-md-2 d-flex align-items-end">
                <button type="button" class="btn btn-secondary w-100" onclick="filterMovements()">
                    <i class="bi bi-search"></i> بحث
                </button>
            </div>

        </div>
    </div>
</div>
