@extends('layouts.app')

@section('title', 'حركات المخزون | نظام ERP')

@section('content')

<div class="container-fluid py-3">

    <div class="d-flex justify-content-between align-items-center mb-3">

        <div>
            <h4 class="mb-1">حركة مخزون</h4>
            <small class="text-muted">إدارة حركات وأرصدة المخزون</small>
        </div>

        <div class="btn-group" role="group">

            <button
                type="button"
                id="btnAddSupplyMovement"
                class="btn btn-success"
                onclick="startSupplyMovement()"
            >
                <i class="bi bi-box-arrow-in-down"></i>
                أمر توريد مخزني
            </button>

            <button
                type="button"
                id="btnAddIssueMovement"
                class="btn btn-warning"
                onclick="startIssueMovement()"
            >
                <i class="bi bi-box-arrow-up"></i>
                أمر صرف مخزني
            </button>

            <button
                type="button"
                id="btnPrintMovement"
                class="btn btn-outline-secondary"
                onclick="printMovement()"
            >
                <i class="bi bi-printer"></i>
                طباعة
            </button>

        </div>

    </div>

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