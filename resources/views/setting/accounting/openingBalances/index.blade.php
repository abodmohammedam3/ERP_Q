@extends('layouts.app')

@section('title', 'الارصدة الافتتاحية | نظام ')

@section('content')

<div class="container-fluid py-3">
<div class="d-flex justify-content-between align-items-center mb-4">

    <div>
        <h4 class="mb-1">الأرصدة الافتتاحية</h4>

        <p class="text-muted mb-0">
            إدخال وإدارة أرصدة بداية الفترة المالية
        </p>
    </div>

    <div class="d-flex gap-2">

        <button type="button" class="btn btn-outline-secondary">
            إلغاء
        </button>

        <button type="button" class="btn btn-primary">
            حفظ
        </button>

    </div>

</div>

    {{-- @include('setting.accounting.openingBalances.periodInfo') --}}

    @include('setting.accounting.openingBalances.tabs')


<div class="d-flex justify-content-end align-items-center mt-4">

    <div class="d-flex gap-2">

        <button class="btn btn-outline-secondary">
            حفظ كمسودة
        </button>

        <button class="btn btn-primary">
            اعتماد الأرصدة
        </button>

    </div>

</div>
     @include('setting.accounting.openingBalances.summary')


</div>

@endsection