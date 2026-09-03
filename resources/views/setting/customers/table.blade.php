<!-- ========================= -->
<!-- جدول عرض العملاء -->
<!-- ========================= -->
<div class="card">
    <div class="card-header">
        <i class="bi bi-list-ul"></i> قائمة العملاء
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-bordered mb-0 align-middle" id="customersTable">
                <thead class="table-light">
                    <tr class="text-center">
                        <th>الرقم</th>
                        <th>اسم العميل</th>
                        <th>الهاتف</th>
                        <th>العنوان</th>
                        <th>الحالة</th>
                        <th class="no-print">الإجراءات</th>
                    </tr>
                </thead>
                <tbody id="customersTableBody">

                    @forelse($customers ?? [] as $customer)
                        <tr class="text-center customer-row">
                            <td class="row-id">{{ $customer->CustomersID }}</td>
                            <td class="row-name">{{ $customer->CusName }}</td>
                            <td class="row-phone">{{ $customer->CusPhone }}</td>
                            <td class="row-address">{{ $customer->CusAddress }}</td>
                            <td class="row-status" data-status="{{ $customer->CusIsStopeed }}">
                                @if($customer->CusIsStopeed == 1)
                                    <span class="badge bg-danger">متوقف</span>
                                @else
                                    <span class="badge bg-success">نشط</span>
                                @endif
                            </td>
                            <td class="text-center no-print">
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="editCustomer(this)">
                                    <i class="bi bi-pencil"></i> تعديل
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteCustomer(this)">
                                    <i class="bi bi-trash"></i> حذف
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr id="emptyCustomerRow">
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="bi bi-people fs-2 d-block mb-2"></i>
                                لا يوجد عملاء مسجلون
                            </td>
                        </tr>
                    @endforelse

                </tbody>
            </table>
        </div>
    </div>
</div>
