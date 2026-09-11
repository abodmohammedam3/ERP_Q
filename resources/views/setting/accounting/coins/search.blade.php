{{-- قسم البحث --}}
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">

            <div class="col-md-6">
                <label class="form-label" for="searchCoinInput">البحث</label>
                <input
                    type="text"
                    id="searchCoinInput"
                    class="form-control"
                    placeholder="ابحث باسم العملة أو رمزها..."
                    onkeyup="filterCoins()"
                >
            </div>

            <div class="col-md-6">
                <label class="form-label" for="statusCoinFilter">الحالة</label>
                <select
                    id="statusCoinFilter"
                    class="form-select"
                    onchange="filterCoins()"
                >
                    <option value="">جميع العملات</option>
                    <option value="active">نشطة</option>
                    <option value="inactive">غير نشطة</option>
                </select>
            </div>

        </div>
    </div>
</div>