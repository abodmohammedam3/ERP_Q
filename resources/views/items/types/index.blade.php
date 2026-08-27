@extends('layouts.app')

@section('title', 'انواع الاصناف')

@section('content')

<div class="container-fluid py-3">

    <div class="d-flex justify-content-between align-items-center mb-3">

        <div>

            <h4 class="mb-1">
                <i class="bi bi-tags"></i>
                الأنواع
            </h4>

            <small class="text-muted">
                إدارة أنواع الأصناف
            </small>

        </div>

        <button type="button" class="btn btn-primary">

            <i class="bi bi-plus-lg"></i>
            إضافة نوع

        </button>

    </div>


    <div class="card mb-3">

        <div class="card-body">

            <div class="row g-2 align-items-end">

                <div class="col-md-6">

                    <label class="form-label">
                        البحث
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        placeholder="ابحث باسم النوع..."
                    >

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
            قائمة الأنواع

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
                                اسم النوع
                            </th>

                            <th class="text-center">
                                الإجراءات
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        @forelse($types ?? [] as $type)

                            <tr>

                                <td class="text-center">
                                    {{ $type->typeID }}
                                </td>

                                <td>
                                    {{ $type->typeName2 }}
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
                                    colspan="3"
                                    class="text-center text-muted py-5"
                                >

                                    <i class="bi bi-tags fs-2 d-block mb-2"></i>

                                    لا توجد أنواع مسجلة

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