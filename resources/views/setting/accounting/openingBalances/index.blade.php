@extends('layouts.app')

@section('title', 'الأرصدة الافتتاحية')

@section('content')
<div class="container-fluid py-3">

    {{-- العنوان --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">
                <i class="bi bi-journal-bookmark"></i>
                الأرصدة الافتتاحية
            </h4>
            <small class="text-muted">
                إدارة الأرصدة الافتتاحية للصناديق والبنوك والعملاء والموردين
            </small>
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

    {{-- الأجزاء --}}
    @include('setting.accounting.openingBalances.tabs')
    @include('setting.accounting.openingBalances.search')
    @include('setting.accounting.openingBalances.table')
    @include('setting.accounting.openingBalances.footer')

</div>

{{-- المودلات --}}
@include('setting.accounting.openingBalances.addUpdate')
@include('setting.accounting.openingBalances.accountPicker')
@include('setting.accounting.openingBalances.deletModel')

@endsection

@push('scripts')
<script>
    window.OB_SYSTEM_CURRENCY_CODE = @json($systemCurrencyCode ?? '');
</script>


<script>
    window.OB_SYSTEM_CURRENCY_CODE = @json($systemCurrencyCode ?? '');
</script>
@vite(['resources/js/pages/opening-balances.js'])
@endpush