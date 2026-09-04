@extends('layouts.app')

@section('title', 'البنوك')

@section('content')

<div class="container-fluid py-3">

    <div class="d-flex justify-content-between align-items-center mb-3">

        <div>

            <h4 class="mb-1">
                <i class="bi bi-bank"></i>
                البنوك
            </h4>

            <small class="text-muted">
                إدارة البنوك المسجلة في النظام
            </small>

        </div>

        <div class="d-flex gap-2">

            <button
                type="button"
                class="btn btn-secondary"
                id="printBanksBtn"
            >
                <i class="bi bi-printer"></i>
                طباعة
            </button>

            <button
                type="button"
                class="btn btn-primary"
                id="addBankBtn"
            >
                <i class="bi bi-plus-lg"></i>
                إضافة بنك
            </button>

        </div>

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
                        id="bankSearch"
                        placeholder="ابحث باسم البنك..."
                    >

                </div>

                <div class="col-md-auto">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        id="searchBankBtn"
                    >
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
            قائمة البنوك

        </div>

        <div class="card-body p-0">

            <div class="table-responsive">

                <table
                    class="table table-hover table-bordered mb-0 align-middle"
                    id="banksTable"
                >

                    <thead class="table-light">

                        <tr>

                            <th class="text-center">
                                #
                            </th>

                            <th>
                                اسم البنك
                            </th>

                            <th>
                                رقم الحساب
                            </th>

                            <th>
                                رقم الحساب التحليلي
                            </th>

                            <!-- عمود جديد: عملة الحساب -->
                            <th>
                                عملة الحساب
                            </th>

                            <th class="text-center">
                                الإجراءات
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        @forelse($banks ?? [] as $bank)

                            <tr>

                                <td class="text-center">
                                    {{ $bank->bankID }}
                                </td>

                                <td>
                                    {{ $bank->bankName2 }}
                                </td>

                                <!-- سيتم ملؤها بواسطة JS -->
                                <td></td>
                                <td></td>
                                <td></td>

                                <td class="text-center">

                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-primary edit-bank"
                                        data-id="{{ $bank->bankID }}"
                                    >
                                        <i class="bi bi-pencil"></i>
                                        تعديل
                                    </button>

                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-danger delete-bank"
                                        data-id="{{ $bank->bankID }}"
                                    >
                                        <i class="bi bi-trash"></i>
                                        حذف
                                    </button>

                                </td>

                            </tr>

                        @empty

                            <tr>

                                <td
                                    colspan="6"   <!-- تغيير من 5 إلى 6 -->
                                    class="text-center text-muted py-5"
                                >

                                    <i class="bi bi-bank fs-2 d-block mb-2"></i>

                                    لا توجد بنوك مسجلة

                                </td>

                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

<!-- نافذة منبثقة (مودال) لإضافة/تعديل بنك -->
<div class="modal fade" id="bankModal" tabindex="-1" aria-labelledby="bankModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bankModalLabel">إضافة بنك</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="bankForm">
                    <input type="hidden" id="bankId">

                    <div class="mb-3">
                        <label for="bankName" class="form-label">اسم البنك <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="bankName" required>
                    </div>

                    <div class="mb-3">
                        <label for="bankAccount" class="form-label">رقم الحساب <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="bankAccount" required>
                    </div>

                    <div class="mb-3">
                        <label for="bankAnalytical" class="form-label">رقم الحساب التحليلي</label>
                        <input type="text" class="form-control" id="bankAnalytical" readonly>
                        <small class="form-text text-muted">يتم توليده تلقائياً عند الإضافة.</small>
                    </div>

                    <!-- حقل العملة -->
                    <div class="mb-3">
                        <label for="bankCurrency" class="form-label">عملة الحساب <span class="text-danger">*</span></label>
                        <select class="form-select" id="bankCurrency" required>
                            <option value="">اختر العملة</option>
                            <option value="ريال يمني">ريال يمني</option>
                            <option value="ريال سعودي">ريال سعودي</option>
                            <option value="دولار أمريكي">دولار أمريكي</option>
                            <option value="يورو">يورو</option>
                            <option value="جنيه إسترليني">جنيه إسترليني</option>
                        </select>
                    </div>

                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-primary" id="saveBankBtn">حفظ</button>
            </div>
        </div>
    </div>
</div>

@endsection
@push('scripts')

<script src="{{ asset('js/banks.js') }}"></script>

@endpush