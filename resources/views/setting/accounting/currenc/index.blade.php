@extends('layouts.app')

@section('title', 'العملات | نظام ERP')

@section('content')

<div class="container-fluid py-3">

    {{-- عنوان الصفحة --}}
    <div class="d-flex justify-content-between align-items-center mb-3">

        <div>
            <h4 class="mb-1">
                <i class="bi bi-currency-exchange"></i>
                العملات
            </h4>

            <p class="text-muted mb-0">
                إدارة العملات وأسعار الصرف المستخدمة في النظام
            </p>
        </div>

        <button type="button"
                class="btn btn-primary"
                data-bs-toggle="modal"
                data-bs-target="#coinModal">

            <i class="bi bi-plus-lg"></i>
            إضافة عملة

        </button>

    </div>


    {{-- شريط البحث --}}
    <div class="card mb-3">

        <div class="card-body">

            <div class="row g-2 align-items-end">

                <div class="col-md-5">

                    <label class="form-label">
                        البحث
                    </label>

                    <input type="text"
                           class="form-control"
                           name="search"
                           placeholder="ابحث باسم العملة أو رمزها">

                </div>

                <div class="col-md-3">

                    <label class="form-label">
                        الحالة
                    </label>

                    <select class="form-select" name="status">

                        <option value="">جميع العملات</option>
                        <option value="active">نشطة</option>
                        <option value="inactive">غير نشطة</option>

                    </select>

                </div>

                <div class="col-md-auto">

                    <button type="button"
                            class="btn btn-secondary">

                        <i class="bi bi-search"></i>
                        بحث

                    </button>

                </div>

                <div class="col-md-auto">

                    <button type="button"
                            class="btn btn-outline-secondary">

                        <i class="bi bi-arrow-clockwise"></i>
                        إعادة تعيين

                    </button>

                </div>

            </div>

        </div>

    </div>


    {{-- جدول العملات --}}
    <div class="card">

        <div class="card-header d-flex justify-content-between align-items-center">

            <span>
                <i class="bi bi-list-ul"></i>
                قائمة العملات
            </span>

            <span class="badge bg-secondary">
                0 عملة
            </span>

        </div>

        <div class="card-body p-0">

            <div class="table-responsive">

                <table class="table table-hover table-bordered align-middle mb-0">

                    <thead class="table-light">

                        <tr>

                            <th class="text-center">
                                #
                            </th>

                            <th>
                                اسم العملة
                            </th>

                            <th>
                                رمز العملة
                            </th>

                            <th>
                                سعر الصرف
                            </th>

                            <th>
                                العملة الأساسية
                            </th>

                            <th>
                                الحالة
                            </th>

                            <th class="text-center">
                                الإجراءات
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        {{-- سيتم جلب البيانات من قاعدة البيانات لاحقًا --}}

                        <tr>

                            <td colspan="7"
                                class="text-center text-muted py-5">

                                <i class="bi bi-currency-exchange fs-2 d-block mb-2"></i>

                                لا توجد عملات لعرضها

                            </td>

                        </tr>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>



{{-- ========================================================= --}}
{{-- نافذة إضافة / تعديل العملة --}}
{{-- ========================================================= --}}

<div class="modal fade"
     id="coinModal"
     tabindex="-1"
     aria-labelledby="coinModalLabel"
     aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content">

            {{-- رأس النافذة --}}
            <div class="modal-header">

                <h5 class="modal-title" id="coinModalLabel">

                    <i class="bi bi-currency-exchange"></i>
                    إضافة عملة

                </h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="إغلاق">
                </button>

            </div>


            {{-- محتوى النافذة --}}
            <div class="modal-body">

                <form id="coinForm">

                    <div class="row g-3">

                        {{-- اسم العملة --}}
                        <div class="col-md-6">

                            <label class="form-label">
                                اسم العملة
                                <span class="text-danger">*</span>
                            </label>

                            <input type="text"
                                   class="form-control"
                                   name="coin_name"
                                   placeholder="مثال: الريال اليمني"
                                   required>

                        </div>


                        {{-- رمز العملة --}}
                        <div class="col-md-3">

                            <label class="form-label">
                                رمز العملة
                                <span class="text-danger">*</span>
                            </label>

                            <input type="text"
                                   class="form-control"
                                   name="coin_symbol"
                                   placeholder="مثال: YER"
                                   required>

                        </div>


                        {{-- سعر الصرف --}}
                        <div class="col-md-6">

                            <label class="form-label">
                                سعر الصرف
                                <span class="text-danger">*</span>
                            </label>

                            <input type="number"
                                   class="form-control"
                                   name="exchange_rate"
                                   step="0.000001"
                                   min="0"
                                   placeholder="أدخل سعر الصرف"
                                   required>

                            <div class="form-text">
                                سعر العملة مقارنة بالعملة الأساسية.
                            </div>

                        </div>


                        {{-- العملة الأساسية --}}
                        <div class="col-md-3">

                            <label class="form-label">
                                العملة الأساسية
                            </label>

                            <select class="form-select"
                                    name="is_base_currency">

                                <option value="0">
                                    لا
                                </option>

                                <option value="1">
                                    نعم
                                </option>

                            </select>

                        </div>


                        {{-- الحالة --}}
                        <div class="col-md-3">

                            <label class="form-label">
                                الحالة
                            </label>

                            <select class="form-select"
                                    name="status">

                                <option value="1">
                                    نشطة
                                </option>

                                <option value="0">
                                    غير نشطة
                                </option>

                            </select>

                        </div>

                    </div>

                </form>

            </div>


            {{-- أزرار النافذة --}}
            <div class="modal-footer">

                <button type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal">

                    <i class="bi bi-x-lg"></i>
                    إلغاء

                </button>

                <button type="button"
                        class="btn btn-success"
                        id="saveCoinBtn">  <!-- إضافة معرف مميز -->

                    <i class="bi bi-check-lg"></i>
                    حفظ

                </button>

            </div>

        </div>

    </div>

</div>

@endsection
@push('scripts')
    <script src="{{ asset('js/currency.js') }}"></script>
@endpush