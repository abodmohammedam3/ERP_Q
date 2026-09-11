@extends('layouts.app')

@section('title', 'العملات')

@section('content')

<div class="container-fluid py-3">

    {{-- عنوان الشاشة --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">
                <i class="bi bi-currency-exchange"></i>
                العملات
            </h4>
            <small class="text-muted">إدارة العملات وأسعار الصرف المستخدمة في النظام</small>
        </div>

        <div class="d-flex gap-2">
            <button type="button" class="btn btn-secondary" onclick="printCoins()">
                <i class="bi bi-printer"></i> طباعة
            </button>
            <button type="button" class="btn btn-primary" onclick="openCoinModal()">
                <i class="bi bi-plus-lg"></i> إضافة عملة
            </button>
        </div>
    </div>

    {{-- استدعاء ملفات البحث والجدول --}}
    @include('setting.accounting.coins.search')
    @include('setting.accounting.coins.table')
    @include('setting.accounting.coins.addUbdate')
    @include('setting.accounting.coins.deletModel')

</div>


@push('scripts')
    <script src="{{ asset('js/coins.js') }}"></script>
@endpush

@endsection