@extends('layouts.app')

@section('title', 'الصناديق')

@section('content')

<div class="container-fluid py-3">

    {{-- عنوان الشاشة --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">
                <i class="bi bi-safe2"></i>
                الصناديق
            </h4>
            <small class="text-muted">إدارة الصناديق النقدية المرتبطة بالنظام</small>
        </div>

        <div class="d-flex gap-2">
            <button type="button" class="btn btn-secondary" onclick="printBoxes()">
                <i class="bi bi-printer"></i> طباعة
            </button>
            <button type="button" class="btn btn-primary" onclick="openBoxModal()">
                <i class="bi bi-plus-lg"></i> إضافة صندوق
            </button>
        </div>
    </div>

    @include('setting.accounting.boxes.search')
    @include('setting.accounting.boxes.table')
    @include('setting.accounting.boxes.addUbdate')
    @include('setting.accounting.boxes.deletModel')

</div>

@push('scripts')
    <script src="{{ asset('js/boxes.js') }}"></script>
@endpush

@endsection