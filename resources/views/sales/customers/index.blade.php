@extends('layouts.app')

@section('title', 'العملاء')

@section('content')

<div class="container-fluid py-3">

    <div class="d-flex justify-content-between align-items-center mb-3">

        <div>

            <h4 class="mb-1">
                <i class="bi bi-people"></i>
                العملاء
            </h4>

            <small class="text-muted">
                إدارة العملاء المسجلين في النظام
            </small>

        </div>

        <button type="button" class="btn btn-primary">

            <i class="bi bi-plus-lg"></i>
            إضافة عميل

        </button>

    </div>


    <div class="card mb-3">

        <div class="card-body">

            <div class="row g-2 align-items-end">

                <div class="col-md-5">

                    <label class="form-label">
                        البحث
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        placeholder="اسم العميل أو رقم الهاتف..."
                    >

                </div>

                <div class="col-md-3">

                    <label class="form-label">
                        الحالة
                    </label>

                    <select class="form-select">

                        <option value="">
                            جميع الحالات
                        </option>

                        <option value="1">
                            متوقف
                        </option>

                        <option value="0">
                            نشط
                        </option>

                    </select>

                </div>

                <div class="col-md-auto">

                    <button type="button" class="btn btn-secondary">

                        <i class="bi bi-search"></i>
                        بحث

                    </button>

                </div>

            </div>

        </div>

    </div>


    <div class="card">

        <div class="card-header">

            <i class="bi bi-list-ul"></i>
            قائمة العملاء

        </div>

        <div class="card-body p-0">

            <div class="table-responsive">

                <table class="table table-hover table-bordered mb-0 align-middle">

                    <thead class="table-light">

                        <tr  class="text-center">

                            <th class="text-center">
                                الرقم
                            </th>

                            <th>
                                اسم العميل
                            </th>

                            <th>
                                الهاتف
                            </th>

                            <th>
                                العنوان
                            </th>

                            <th class="text-center">
                                الحالة
                            </th>

                            <th class="text-center">
                                الإجراءات
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        @forelse($customers ?? [] as $customer)

                            <tr>

                                <td class="text-center">
                                    {{ $customer->CustomersID }}
                                </td>

                                <td>
                                    {{ $customer->CusName }}
                                </td>

                                <td>
                                    {{ $customer->CusPhone }}
                                </td>

                                <td>
                                    {{ $customer->CusAddress }}
                                </td>

                                <td class="text-center">

                                    @if($customer->CusIsStopeed)

                                        <span class="badge bg-danger">
                                            متوقف
                                        </span>

                                    @else

                                        <span class="badge bg-success">
                                            نشط
                                        </span>

                                    @endif

                                </td>

                                <td class="text-center">

                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-primary"
                                    >
                                        <i class="bi bi-pencil"></i>
                                        تعديل
                                    </button>

                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-danger"
                                    >
                                        <i class="bi bi-trash"></i>
                                        حذف
                                    </button>

                                </td>

                            </tr>

                        @empty

                            <tr>

                                <td
                                    colspan="6"
                                    class="text-center text-muted py-5"
                                >

                                    <i class="bi bi-people fs-2 d-block mb-2"></i>

                                    لا يوجد عملاء مسجلون

                                </td>

                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

@endsection