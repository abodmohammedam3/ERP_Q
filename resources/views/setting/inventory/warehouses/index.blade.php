@extends('layouts.app')

@section('title', 'المخازن')

@section('content')

<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1"><i class="bi bi-building"></i> المخازن</h4>
            <small class="text-muted">إدارة المخازن المرتبطة بالنظام</small>
        </div>
        <div class="btn-group">
            @if($hasParent)
                <button type="button" class="btn btn-outline-secondary" onclick="printStocks()">
                    <i class="bi bi-printer"></i> طباعة
                </button>
                <button type="button" class="btn btn-primary" onclick="openStockModal()">
                    <i class="bi bi-plus-lg"></i> إضافة مخزن
                </button>
            @endif
        </div>
    </div>

    @if(!$hasParent)
        <div class="alert alert-warning mb-3">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>تنبيه:</strong> لم يتم العثور على حساب "المخازن" في دليل الحسابات.
            يرجى إنشاء حساب باسم "المخازن" كحساب فرعي تحت "أصول متداولة" لإمكانية إدارة المخازن.
            <br><small>حالياً الشاشة في وضع <strong>العرض فقط</strong>.</small>
        </div>
    @endif

    @include('setting.inventory.warehouses.search')
    @include('setting.inventory.warehouses.table')
</div>

<!-- مودال الإضافة/التعديل -->
<div class="modal fade" id="stockModal" tabindex="-1" aria-labelledby="stockModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="stockModalLabel">إضافة مخزن جديد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="stockForm">
                    <input type="hidden" id="stockID">
                    <div class="mb-3">
                        <label for="stockName" class="form-label">اسم المخزن <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="stockName" required>
                    </div>
                    <div class="mb-3">
                        <label for="accountDisplay" class="form-label">رقم الحساب التحليلي</label>
                        <input type="text" class="form-control" id="accountDisplay" readonly>
                        <small class="text-muted">يتم إنشاؤه تلقائياً عند الإضافة</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-primary" onclick="saveStock()">حفظ</button>
            </div>
        </div>
    </div>
</div>

{{-- نافذة تأكيد الحذف --}}
<div class="delete-confirm-overlay" id="deleteConfirmModal">
    <div class="delete-confirm-box">
        <div class="delete-confirm-icon"><i class="bi bi-trash3"></i></div>
        <h3>حذف المخزن</h3>
        <p>هل أنت متأكد من حذف هذا المخزن؟<br><span>سيتم حذف الحساب المحاسبي المرتبط به أيضاً.</span></p>
        <div class="delete-confirm-actions">
            <button type="button" class="delete-cancel-btn" id="deleteCancelBtn">إلغاء</button>
            <button type="button" class="delete-confirm-btn" id="deleteConfirmBtn">حذف المخزن</button>
        </div>
    </div>
</div>

@push('scripts')
<script src="{{ asset('js/stocks.js') }}"></script>
@endpush

@endsection