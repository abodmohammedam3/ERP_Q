{{-- قسم البحث --}}
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">

            <div class="col-md-6">
                <label class="form-label" for="searchBoxInput">البحث</label>
                <input
                    type="text"
                    id="searchBoxInput"
                    class="form-control"
                    placeholder="ابحث باسم الصندوق..."
                    onkeyup="filterBoxes()"
                >
            </div>

            <div class="col-md-6">
                <label class="form-label" for="statusBoxFilter">العملة</label>
                <select
                    id="statusBoxFilter"
                    class="form-select"
                    onchange="filterBoxes()"
                >
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