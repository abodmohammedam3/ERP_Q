@extends('layouts.app')

@section('title', 'البنوك')

@section('content')

<div class="container-fluid py-3">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">
                <i class="bi bi-bank"></i>
                البنوك
            </h4>
            <small class="text-muted">إدارة البنوك المرتبطة بالنظام</small>
        </div>

        <div class="d-flex gap-2">
            <button type="button" class="btn btn-secondary" onclick="printBanks()">
                <i class="bi bi-printer"></i> طباعة
            </button>
            <button type="button" class="btn btn-primary" onclick="openBankModal()">
                <i class="bi bi-plus-lg"></i> إضافة بنك
            </button>
        </div>
    </div>

    {{-- ⚠️ هذه السطور الأربعة إلزامية --}}
    @include('setting.accounting.banks.search')
    @include('setting.accounting.banks.table')
    @include('setting.accounting.banks.addUbdate')     {{-- ← المودال --}}
    @include('setting.accounting.banks.deletModel')    {{-- ← مودال الحذف --}}

</div>

@push('scripts')
    <script src="{{ asset('js/banks.js') }}"></script>
@endpush

@endsection