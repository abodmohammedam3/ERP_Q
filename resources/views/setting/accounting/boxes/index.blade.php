@extends('layouts.app')

@section('title', 'الصناديق')

@section('content')

<div class="container-fluid py-3">

    <div class="d-flex justify-content-between align-items-center mb-3">

        <div>

            <h4 class="mb-1">
                <i class="bi bi-safe2"></i>
                الصناديق
            </h4>

            <small class="text-muted">
                إدارة الصناديق النقدية المرتبطة بالنظام
            </small>

        </div>

        <button type="button" class="btn btn-primary">

            <i class="bi bi-plus-lg"></i>
            إضافة صندوق

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
                        placeholder="ابحث باسم الصندوق..."
                    >

                </div>

                <div class="col-md-3">

                    <label class="form-label">
                        العملة
                    </label>

                    <select class="form-select">

                        <option value="">
                            جميع العملات
                        </option>

                        @foreach($coins ?? [] as $coin)

                            <option value="{{ $coin->coinsID }}">
                                {{ $coin->coinsCode2 ?? $coin->coinsCode3 }}
                            </option>

                        @endforeach

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
            قائمة الصناديق

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
                                اسم الصندوق
                            </th>

                            <th>
                                العملة
                            </th>

                            <th>
                               سعر الصرف
                            </th>

                            <th class="text-center">
                                الإجراءات
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        @forelse($boxes ?? [] as $box)

                            <tr  class="text-center">

                                <td class="text-center">
                                    {{ $box->boxID }}
                                </td>

                                <td>
                                    {{ $box->boxName2 }}
                                </td>

                                <td>
                                    {{ $box->coin->coinsCode2 ?? $box->coin->coinsCode3 ?? 'غير محددة' }}
                                </td>

                                <td>
                                    {{ $box->account->accName ?? 'غير مرتبط' }}
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
                                    colspan="5"
                                    class="text-center text-muted py-5"
                                >

                                    <i class="bi bi-safe2 fs-2 d-block mb-2"></i>

                                    لا توجد صناديق مسجلة

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

@push('scripts')

<script src="{{ asset('js/boxes.js') }}"></script>

@endpush