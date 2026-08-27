{{-- قسم البحث --}}
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">

            <div class="col-md-6">
                <label class="form-label" for="searchItemInput">البحث</label>
                <input 
                    type="text" 
                    id="searchItemInput"
                    class="form-control" 
                    placeholder="ابحث باسم الصنف..."
                    onkeyup="filterItems()"
                >
            </div>

            <div class="col-md-auto">
                <button type="button" class="btn btn-secondary" onclick="filterItems()">
                    <i class="bi bi-search"></i> بحث
                </button>
            </div>

        </div>
    </div>
</div>
