@extends('layouts.app')

@section('title', 'دليل الحسابات')

@section('content')

    


    {{-- =========================================
         رأس الصفحة
    ========================================== --}}

    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">

        <div>

            <div class="d-flex align-items-center gap-2 mb-1">

                <i class="bi bi-diagram-3 fs-4 text-primary"></i>

                <h4 class="mb-0 fw-bold">
                    دليل الحسابات
                </h4>

            </div>

            <p class="text-body-secondary mb-0">
                إدارة وتنظيم الحسابات.
            </p>

        </div>


        <div class="d-flex flex-wrap gap-2">

            <button
                type="button"
                class="btn btn-primary"
                id="addAccountBtn">

                <i class="bi bi-plus-lg me-1"></i>

                إضافة حساب

            </button>

        </div>

    </div>


    {{-- =========================================
         البحث
    ========================================== --}}

    @include('setting.accounting.chartOfAccounts.search')


    {{-- =========================================
         جدول الحسابات
    ========================================== --}}

    @include('setting.accounting.chartOfAccounts.display')


    {{-- =========================================
         مودال الإضافة والتعديل
    ========================================== --}}

    @include('setting.accounting.chartOfAccounts.addUpdate')

@include('setting.accounting.chartOfAccounts.deletModel')
</div>



{{-- =====================================================
     JavaScript
===================================================== --}}


<script src="{{ asset('js/accounting/chartOfAccounts.js') }}"></script>

@endsection