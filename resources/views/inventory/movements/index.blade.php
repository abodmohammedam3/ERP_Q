@extends('layouts.app')

@section('title', 'حركات المخزون')

@section('content')

<div class="container-fluid py-3">
    {{-- عنوان الشاشة --}}
<div class="d-flex justify-content-between align-items-center mb-3">

    <div>

        <h4 class="mb-1">
            <i class="bi bi-arrow-left-right"></i>
            حركات المخزون
        </h4>

        <small class="text-muted">
            متابعة حركة الأصناف داخل المخازن
        </small>

    </div>

    <div>

        <button type="button" class="btn btn-primary">

            <i class="bi bi-printer"></i>
            طباعة

        </button>

    </div>

</div>


{{-- فلاتر البحث --}}
<div class="card mb-3">

    <div class="card-header">

        <i class="bi bi-funnel"></i>
        خيارات البحث

    </div>

    <div class="card-body">

        <div class="row g-3">

            {{-- المخزن --}}
            <div class="col-md-3">

                <label class="form-label">
                    المخزن
                </label>

                <select class="form-select">

                    <option value="">
                        جميع المخازن
                    </option>

                    @foreach($stocks ?? [] as $stock)

                        <option value="{{ $stock->StockID }}">
                            {{ $stock->StockName2 }}
                        </option>

                    @endforeach

                </select>

            </div>


            {{-- نوع الحركة --}}
            <div class="col-md-3">

                <label class="form-label">
                    نوع الحركة
                </label>

                <select class="form-select">

                    <option value="">
                        جميع الحركات
                    </option>

                    <option value="purchase">
                        شراء
                    </option>

                    <option value="sales">
                        بيع
                    </option>

                    <option value="purchase_return">
                        مرتجع شراء
                    </option>

                    <option value="sales_return">
                        مرتجع بيع
                    </option>

                </select>

            </div>


            {{-- من تاريخ --}}
            <div class="col-md-2">

                <label class="form-label">
                    من تاريخ
                </label>

                <input
                    type="date"
                    class="form-control"
                >

            </div>


            {{-- إلى تاريخ --}}
            <div class="col-md-2">

                <label class="form-label">
                    إلى تاريخ
                </label>

                <input
                    type="date"
                    class="form-control"
                >

            </div>


            {{-- البحث --}}
            <div class="col-md-2 d-flex align-items-end">

                <button
                    type="button"
                    class="btn btn-secondary w-100"
                >

                    <i class="bi bi-search"></i>
                    بحث

                </button>

            </div>

        </div>

    </div>

</div>


{{-- جدول الحركات --}}
<div class="card">

    <div class="card-header d-flex justify-content-between align-items-center">

        <span>

            <i class="bi bi-clock-history"></i>
            سجل حركات المخزون

        </span>

        <span class="badge bg-secondary">

            {{ isset($movements) ? $movements->count() : 0 }}

        </span>

    </div>


    <div class="card-body p-0">

        <div class="table-responsive">

            <table class="table table-hover table-bordered mb-0 align-middle">

                <thead class="table-light">

                    <tr>

                        <th class="text-center">
                            #
                        </th>

                        <th>
                            التاريخ
                        </th>

                        <th>
                            المخزن
                        </th>

                        <th>
                            نوع الحركة
                        </th>

                        <th>
                            الاتجاه
                        </th>

                        <th>
                            البيان
                        </th>

                        <th class="text-center">
                            التفاصيل
                        </th>

                    </tr>

                </thead>


                <tbody>

                    @forelse($movements ?? [] as $movement)

                        <tr>

                            {{-- رقم الحركة --}}
                            <td class="text-center">

                                {{ $movement->StockMovementsID }}

                            </td>


                            {{-- التاريخ --}}
                            <td>

                                {{ $movement->MovementsDate2 }}

                            </td>


                            {{-- المخزن --}}
                            <td>

                                {{ $movement->stock->StockName2 ?? 'غير محدد' }}

                            </td>


                            {{-- نوع الحركة --}}
                            <td>

                                {{ $movement->TypeOfMovement2 }}

                            </td>


                            {{-- اتجاه الحركة --}}
                            <td>

                                @if($movement->directionOfMovements2)

                                    <span class="badge bg-success">

                                        دخول

                                    </span>

                                @else

                                    <span class="badge bg-danger">

                                        خروج

                                    </span>

                                @endif

                            </td>


                            {{-- البيان --}}
                            <td>

                                {{ $movement->MovementStatement2 }}

                            </td>


                            {{-- التفاصيل --}}
                            <td class="text-center">

                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-primary"
                                >

                                    <i class="bi bi-eye"></i>
                                    عرض

                                </button>

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="7"
                                class="text-center text-muted py-5"
                            >

                                <i class="bi bi-arrow-left-right fs-2 d-block mb-2"></i>

                                لا توجد حركات مخزون

                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>

</div>


@endsection
