{{-- قسم البحث --}}
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">

            {{-- اسم العميل --}}
            <div class="col-12 col-md-4">
                <label for="searchName" class="form-label">اسم العميل</label>
                <input
                    type="text"
                    class="form-control"
                    id="searchName"
                    name="search_name"
                    placeholder="ابحث باسم العميل..."
                    autocomplete="off">
            </div>

            {{-- رقم الهاتف --}}
            <div class="col-12 col-md-4">
                <label for="searchPhone" class="form-label">رقم الهاتف</label>
                <input
                    type="text"
                    class="form-control"
                    id="searchPhone"
                    name="search_phone"
                    placeholder="ابحث برقم الهاتف..."
                    autocomplete="off">
            </div>

            {{-- رقم الحساب المحاسبي --}}
            <div class="col-12 col-md-4">
                <label for="searchCode" class="form-label">رقم الحساب</label>
                <input
                    type="text"
                    class="form-control"
                    id="searchCode"
                    name="search_code"
                    placeholder="رقم الحساب المرتبط..."
                    autocomplete="off">
            </div>

        </div>
    </div>
</div>