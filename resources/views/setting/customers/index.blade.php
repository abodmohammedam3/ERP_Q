@extends('layouts.app')

@section('title', 'العملاء')

@section('content')

<div class="container-fluid py-3">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">
                <i class="bi bi-people"></i> العملاء
            </h4>
            <small class="text-muted">إدارة العملاء المسجلين في النظام</small>
        </div>

        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary" onclick="printCustomers()">
                <i class="bi bi-printer"></i> طباعة القائمة
            </button>
            <button type="button" class="btn btn-primary" onclick="openCustomerModal()">
                <i class="bi bi-plus-lg"></i> إضافة عميل
            </button>
        </div>
    </div>

    <!-- استدعاء ملف البحث وملف الجدول -->
    @include('setting.customers.search')
    @include('setting.customers.table')

</div>

<!-- النافذة المنبثقة (Modal) لإضافة/تعديل عميل -->
<div class="modal fade" id="customerModal" tabindex="-1" aria-labelledby="customerModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="customerModalLabel">إضافة عميل جديد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="customerForm">
                    <input type="hidden" id="customerID">
                    
                    <div class="mb-3">
                        <label for="cusName" class="form-label">اسم العميل <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="cusName" required>
                    </div>

                    <div class="mb-3">
                        <label for="cusPhone" class="form-label">رقم الهاتف</label>
                        <input type="text" class="form-control" id="cusPhone">
                    </div>

                    <div class="mb-3">
                        <label for="cusAddress" class="form-label">العنوان</label>
                        <input type="text" class="form-control" id="cusAddress">
                    </div>

                    <div class="mb-3">
                        <label for="cusStatus" class="form-label">الحالة</label>
                        <select class="form-select" id="cusStatus">
                            <option value="0">نشط</option>
                            <option value="1">متوقف</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-primary" onclick="saveCustomer()">حفظ البيانات</button>
            </div>
        </div>
    </div>
</div>



@endsection
@push('scripts')

<script src="{{ asset('js/customer.js') }}"></script>

@endpush
