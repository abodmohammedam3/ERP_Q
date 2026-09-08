{{-- قسم البحث --}}
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-12">
                <label class="form-label" for="searchItemInput">البحث</label>
                <input 
                    type="text" 
                    id="searchItemInput"
                    class="form-control" 
                    placeholder="ابحث باسم الصنف..."
                    onkeyup="filterItems()"
                >
            </div>
        </div>
    </div>
</div>