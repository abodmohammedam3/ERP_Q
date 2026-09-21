@extends('layouts.app')

@section('title', 'حركات المخزون | نظام ERP')

@section('content')

<div class="container-fluid">

    {{-- قسم البحث --}}
    @include('operation.movements.search')

    {{-- قسم أزرار العمليات --}}
    @include('operation.movements.operations')

    {{-- رأس الحركة --}}
    @include('operation.movements.head')

    {{-- تفاصيل الحركة --}}
    @include('operation.movements.details')

</div>


{{-- ===================================================== --}}
{{-- النوافذ المنبثقة --}}
{{-- ===================================================== --}}

@include('operation.movements.models.item')
@include('operation.movements.models.type')
@include('operation.movements.models.warehouses')
@include('operation.movements.models.unit')

@endsection


@push('scripts')

    @vite(['resources/js/pages/movements.js'])

@endpush