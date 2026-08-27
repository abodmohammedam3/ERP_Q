<!-- ========================= -->
<!-- قسم البحث والفلترة -->
<!-- ========================= -->
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">

            <div class="col-md-5">
                <label class="form-label" for="searchCustomerInput">البحث</label>
                <input 
                    type="text" 
                    id="searchCustomerInput"
                    class="form-control" 
                    placeholder="اسم العميل أو رقم الهاتف..."
                    onkeyup="filterCustomers()"
                >
            </div>

            <div class="col-md-3">
                <label class="form-label" for="filterCustomerStatus">الحالة</label>
                <select class="form-select" id="filterCustomerStatus" onchange="filterCustomers()">
                    <option value="">جميع الحالات</option>
                    <option value="1">متوقف</option>
                    <option value="0">نشط</option>
                </select>
            </div>

            <div class="col-md-auto">
                <button type="button" class="btn btn-secondary" onclick="filterCustomers()">
                    <i class="bi bi-search"></i> بحث
                </button>
            </div>

        </div>
    </div>
</div>
