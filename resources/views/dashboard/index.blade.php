@extends('layouts.app')

@section('title', 'لوحة التحكم ')

@section('content')
<div class="py-3">

    <!-- ========================================= -->
    <!-- عنوان لوحة التحكم -->
    <!-- ========================================= -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h4 class="mb-1">
                لوحة التحكم
            </h4>

            <p class="text-muted mb-0">
                ملخص العمليات والحركة الحالية في النظام
            </p>

        </div>

        <div>

            <span class="badge text-bg-secondary">

                <i class="bi bi-calendar3"></i>

                <span id="dashboardDate">
                    اليوم
                </span>

            </span>

        </div>

    </div>
    @include('dashboard.SystemIndicators')
    @include('dashboard.summaryOperations')
    @include('dashboard.SupplyDistribution')
    {{-- @include('dashboard.Inventory') --}}
    {{-- @include('dashboard.SystemAlerts') --}}

</div>                
@endsection
