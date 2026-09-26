@extends('layouts.app')

@section('title', 'حركات المخزون | نظام ERP')

@section('content')

<div class="container-fluid">

    {{-- ═══════════════════════════════════════════════ --}}
    {{-- شريط التابات                                    --}}
    {{-- ═══════════════════════════════════════════════ --}}
    @include('operation.movements.tabs')

    {{-- ═══════════════════════════════════════════════ --}}
    {{-- محتوى التابات                                    --}}
    {{-- ═══════════════════════════════════════════════ --}}
    <div class="tab-content" id="movementTabsContent">

        {{-- ─────────────────────────────────────────
             Tab 1: حركات المخزون
             ───────────────────────────────────────── --}}
        <div class="tab-pane fade show active"
             id="tab-movements"
             role="tabpanel">

            {{-- قسم البحث --}}
            @include('operation.movements.search')

            {{-- قسم أزرار العمليات --}}
            @include('operation.movements.operations')

            {{-- رأس الحركة --}}
            @include('operation.movements.head')

            {{-- تفاصيل الحركة --}}
            @include('operation.movements.details')

        </div>

        {{-- ─────────────────────────────────────────
             Tab 2: أرصدة المخزون
             ───────────────────────────────────────── --}}
        <div class="tab-pane fade"
             id="tab-balances"
             role="tabpanel">

            @include('operation.movements.balances.table')

        </div>

    </div>

</div>


{{-- ===================================================== --}}
{{-- نوافذ الحركات الحالية (Item, Type, Warehouse, Unit)   --}}
{{-- ===================================================== --}}

@include('operation.movements.models.item')
@include('operation.movements.models.type')
@include('operation.movements.models.warehouses')
@include('operation.movements.models.unit')


{{-- ===================================================== --}}
{{-- نوافذ أرصدة المخزون (التسعير + الفرز)                --}}
{{-- ===================================================== --}}

@include('operation.movements.balances.modals')

@endsection


@push('scripts')

    @vite(['resources/js/pages/movements.js'])

@endpush