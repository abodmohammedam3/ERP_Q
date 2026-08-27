{{-- شريط البحث --}}
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">

            <div class="col-md-6">
                <label class="form-label" for="searchStockInput">البحث</label>
                <input 
                    type="text" 
                    id="searchStockInput"
                    class="form-control" 
                    placeholder="ابحث باسم المخزن..."
                    onkeyup="filterStocks()"
                >
            </div>

            <div class="col-md-auto">
                <button type="button" class="btn btn-secondary" onclick="filterStocks()">
                    <i class="bi bi-search"></i> بحث
                </button>
            </div>

        </div>
    </div>
</div>
