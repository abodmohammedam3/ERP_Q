{{-- قسم البحث --}}
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-12">
                <label class="form-label" for="searchTypeInput">البحث</label>
                <input 
                    type="text" 
                    id="searchTypeInput"
                    class="form-control" 
                    placeholder="ابحث باسم النوع..."
                    onkeyup="filterTypes()"
                >
            </div>
        </div>
    </div>
</div>