@extends('layouts.app')

@section('title', 'دليل الحسابات | نظام ERP')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">دليل الحسابات</h4>
            <small class="text-muted">إدارة الحسابات الرئيسية والفرعية.</small>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAccountModal">
            + إضافة حساب جديد
        </button>
        </div>
    </div>

    <div class="card">
        <div class="card-body text-muted">
           @include('accounting.chartOfAccounts.search')
           @include('accounting.chartOfAccounts.display')
           @include('accounting.chartOfAccounts.addUpdate')
        </div>
    </div>
</div>
@endsection
    