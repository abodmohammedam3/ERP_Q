<!-- ===================================== -->
<!-- رأس الفاتورة -->
<!-- ===================================== -->
<div class="card mb-3">
    <div class="card-header">
        <strong>بيانات الفاتورة</strong>
    </div>
    <div class="card-body">
        <div class="row g-3">
            
            <div class="col-md-2">
                <label for="SalesInvoiceID" class="form-label">رقم السجل</label>
                <input type="number" class="form-control" id="SalesInvoiceID" readonly>
            </div>

            <div class="col-md-3">
                <label for="SalesInvoiceNo" class="form-label">رقم الفاتورة</label>
                <input type="text" class="form-control" id="SalesInvoiceNo">
            </div>

            <div class="col-md-3">
                <label for="SalesInvoiceDate" class="form-label">تاريخ الفاتورة</label>
                <input type="date" class="form-control" id="SalesInvoiceDate">
            </div>

            <div class="col-md-4">
                <label for="customerName" class="form-label">العميل</label>
                <div class="input-group">
                    <input type="hidden" id="customerID">
                    <input type="text" class="form-control" id="customerName" placeholder="اختر العميل" readonly>
                    <button type="button" class="btn btn-outline-primary" onclick="selectCustomer()">اختيار</button>
                </div>
            </div>

        </div>
    </div>
</div>
