@extends('layouts.app')

@section('title', 'الوحدات')

@section('content')

<div class="container-fluid py-3">

    {{-- عنوان الشاشة --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">
                <i class="bi bi-rulers"></i>
                الوحدات
            </h4>
            <small class="text-muted">إدارة وحدات القياس المستخدمة في النظام</small>
        </div>

        <div class="d-flex gap-2">
            <button type="button" class="btn btn-secondary" onclick="printUnits()">
                <i class="bi bi-printer"></i> طباعة
            </button>
            <button type="button" class="btn btn-primary" onclick="openUnitModal()">
                <i class="bi bi-plus-lg"></i> إضافة وحدة
            </button>
        </div>
    </div>

    {{-- استدعاء ملفات البحث والجدول --}}
    @include('setting.inventory.units.search')
    @include('setting.inventory.units.table')

</div>

{{-- مودال إضافة/تعديل وحدة --}}
<div class="modal fade" id="unitModal" tabindex="-1" aria-labelledby="unitModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="unitModalLabel">إضافة وحدة جديدة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="unitForm">
                    <input type="hidden" id="unitID">
                    <div class="mb-3">
                        <label for="unitName" class="form-label">اسم الوحدة <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="unitName" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-primary" onclick="saveUnit()">حفظ البيانات</button>
            </div>
        </div>
    </div>
</div>

{{-- نافذة تأكيد الحذف --}}
<div class="delete-confirm-overlay" id="deleteConfirmModal">
    <div class="delete-confirm-box">
        <div class="delete-confirm-icon">
            <i class="bi bi-trash3"></i>
        </div>
        <h3>حذف الوحدة</h3>
        <p>
            هل أنت متأكد من حذف هذه الوحدة؟
            <br>
            <span>لا يمكن التراجع عن هذه العملية بعد تنفيذها.</span>
        </p>
        <div class="delete-confirm-actions">
            <button type="button" class="delete-cancel-btn" id="deleteCancelBtn">إلغاء</button>
            <button type="button" class="delete-confirm-btn" id="deleteConfirmBtn">حذف الوحدة</button>
        </div>
    </div>
</div>

@push('scripts')
    <script src="{{ asset('js/units.js') }}"></script>
@endpush

@endsection