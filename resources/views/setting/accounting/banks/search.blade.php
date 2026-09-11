<div class="card mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label" for="searchBankInput">البحث</label>
                <input type="text" id="searchBankInput" class="form-control"
                       placeholder="ابحث باسم البنك..." onkeyup="filterBanks()">
            </div>

            <div class="col-md-6">
                <label class="form-label" for="statusBankFilter">العملة</label>
                <select id="statusBankFilter" class="form-select" onchange="filterBanks()">
                    <option value="">جميع العملات</option>
                    @foreach($coins ?? [] as $coin)
                        <option value="{{ $coin->coinsID }}">
                            {{ $coin->coinsCode }} - {{ $coin->coinsName }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
</div>