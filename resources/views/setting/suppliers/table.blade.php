<!-- ========================= -->
<!-- جدول عرض الموردين -->
<!-- ========================= -->
<div class="card">
    <div class="card-header">
        <i class="bi bi-list-ul"></i> قائمة الموردين
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-bordered mb-0 align-middle" id="suppliersTable">
                <thead class="table-light">
                    <tr class="text-center">
                        <th>الرقم</th>
                        <th>اسم المورد</th>
                        <th>الهاتف</th>
                        <th>المنطقة</th>
                        <th>رقم الحساب التحليلي</th>
                        <th>الحالة</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody id="suppliersTableBody">

                    @forelse($suppliers ?? [] as $supplier)
                        <tr class="text-center supplier-row">
                            <td class="row-id">{{ $supplier->suplierID }}</td>
                            <td class="row-name">{{ $supplier->supName2 }}</td>
                            <td class="row-phone">{{ $supplier->supPhone2 }}</td>
                            <td class="row-area">{{ $supplier->supArea2 }}</td>
                            <td class="row-analytical">{{ $supplier->analytical_account ?? '' }}</td>
                            <td class="row-status" data-status="{{ $supplier->supStoped2 }}">
                                @if($supplier->supStoped2 == 1)
                                    <span class="badge bg-danger">متوقف</span>
                                @else
                                    <span class="badge bg-success">نشط</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="editSupplier(this)">
                                    <i class="bi bi-pencil"></i> تعديل
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteSupplier(this)">
                                    <i class="bi bi-trash"></i> حذف
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr id="emptyRow">
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="bi bi-truck fs-2 d-block mb-2"></i>
                                لا يوجد موردون مسجلون
                            </td>
                        </tr>
                    @endforelse

                </tbody>
            </table>
        </div>
    </div>
</div>
