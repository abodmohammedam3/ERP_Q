{{-- قسم البحث --}}
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-12">
                <label class="form-label" for="searchUnitInput">البحث</label>
                <input 
                    type="text" 
                    id="searchUnitInput"
                    class="form-control" 
                    placeholder="ابحث باسم الوحدة..."
                    onkeyup="filterUnits()"
                >
            </div>
        </div>
    </div>
</div>

{{-- أزرار التنقل بين الصفحات (limit 100 لكل صفحة) --}}
<div class="d-flex gap-1 justify-content-center mb-3" id="unitsPagination"></div>