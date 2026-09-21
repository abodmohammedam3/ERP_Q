@extends('layouts.app')

@section('title', 'قيود اليومية')

@section('content')
<div class="container-fluid py-3">

    {{-- العنوان --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">
                <i class="bi bi-journal-text"></i>
                قيود اليومية
            </h4>
            <small class="text-muted">
                عرض جميع القيود المحاسبية
            </small>
        </div>

        <div class="d-flex gap-2">
            <button type="button"
                    class="btn btn-outline-secondary"
                    id="btnPrintJournalEntries">
                <i class="bi bi-printer"></i> طباعة
            </button>
        </div>
    </div>

    {{-- الفلاتر --}}
    @include('operation.accounting.journalEntries.filters')

    {{-- الجدول --}}
    @include('operation.accounting.journalEntries.table')

    {{-- الإجماليات --}}
    @include('operation.accounting.journalEntries.footer')

</div>

{{-- مودل التفاصيل --}}
@include('operation.accounting.journalEntries.detailsModal')

@endsection

@push('scripts')
    @vite(['resources/js/pages/journal-entries.js'])
@endpush