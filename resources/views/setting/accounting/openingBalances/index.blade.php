@extends('layouts.app')

@section('title', 'الأرصدة الافتتاحية')

@section('content')
<div class="container-fluid py-3">

    {{-- عنوان الشاشة --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">
                <i class="bi bi-journal-bookmark"></i>
                الأرصدة الافتتاحية
            </h4>
            <small class="text-muted">إدارة الأرصدة الافتتاحية للصناديق والبنوك والعملاء والموردين</small>
        </div>

        <div class="d-flex gap-2">
            <button type="button"
                    class="btn btn-outline-secondary"
                    id="btnPrintOpeningBalances">
                <i class="bi bi-printer"></i> طباعة
            </button>

            <button type="button"
                    class="btn btn-primary"
                    id="btnAddOpeningBalance">
                <i class="bi bi-plus-lg"></i> إضافة رصيد افتتاحي
            </button>
        </div>
    </div>

    {{-- التابات: صناديق / بنوك / عملاء / موردين --}}
    @include('setting.accounting.openingBalances.tabs')

    {{-- شريط البحث --}}
    @include('setting.accounting.openingBalances.search')

    {{-- جدول العرض --}}
    @include('setting.accounting.openingBalances.table')

    {{-- الإجماليات أسفل الجدول --}}
    @include('setting.accounting.openingBalances.footer')

</div>

{{-- المودلات --}}
@include('setting.accounting.openingBalances.addUpdate')
@include('setting.accounting.openingBalances.deletModel')

@endsection

@push('scripts')
<script src="{{ asset('js/accounting/openingBalances.js') }}"></script>
@endpush