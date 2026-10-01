{{-- قسم البحث --}}
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">

            {{-- اسم المورد --}}
            <div class="col-12 col-md-4">
                <label for="searchSupplierName" class="form-label">اسم المورد</label>
                <input
                    type="text"
                    class="form-control"
                    id="searchSupplierName"
                    name="search_name"
                    placeholder="ابحث باسم المورد..."
                    autocomplete="off">
            </div>

            {{-- رقم الهاتف --}}
            <div class="col-12 col-md-4">
                <label for="searchSupplierPhone" class="form-label">رقم الهاتف</label>
                <input
                    type="text"
                    class="form-control"
                    id="searchSupplierPhone"
                    name="search_phone"
                    placeholder="ابحث برقم الهاتف..."
                    autocomplete="off">
            </div>

            {{-- رقم الحساب التحليلي --}}
            <div class="col-12 col-md-4">
                <label for="searchSupplierCode" class="form-label">رقم الحساب</label>
                <input
                    type="text"
                    class="form-control"
                    id="searchSupplierCode"
                    name="search_code"
                    placeholder="رقم الحساب التحليلي..."
                    autocomplete="off">
            </div>

        </div>
    </div>
</div>