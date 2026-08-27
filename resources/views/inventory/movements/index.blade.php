@extends('layouts.app')

@section('title', 'حركات المخزون')

@section('content')

<div class="container-fluid py-3">
    
    {{-- عنوان الشاشة --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">
                <i class="bi bi-arrow-left-right"></i> حركات المخزون
            </h4>
            <small class="text-muted">متابعة حركة الأصناف داخل المخازن</small>
        </div>

        <div>
            <button type="button" class="btn btn-primary" onclick="printMovements()">
                <i class="bi bi-printer"></i> طباعة
            </button>
        </div>
    </div>

    <!-- استدعاء ملف البحث وملف الجدول -->
    @include('inventory.movements.search')
    @include('inventory.movements.table')

</div>


@endsection
