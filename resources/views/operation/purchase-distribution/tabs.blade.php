{{-- الأزرار --}}
<ul class="nav nav-tabs mb-3" id="mainTabs">
    <li class="nav-item">
        <button class="nav-link active" data-tab="distribute" type="button">
            <i class="bi bi-diagram-3"></i> توزيع
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-tab="issue" type="button">
            <i class="bi bi-receipt"></i> إصدار فواتير
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-tab="history" type="button">
            <i class="bi bi-clock-history"></i> سجل التوزيعات
        </button>
    </li>
</ul>

{{-- 1) تبويب التوزيع --}}
<div id="tab-distribute" class="tab-pane-content">
    @include('operation.purchase-distribution.header')
    @include('operation.purchase-distribution.distribution-info')
    <div class="mt-3">@include('operation.purchase-distribution.items-table')</div>
    <div class="mt-3">@include('operation.purchase-distribution.summary')</div>
    <div class="mt-3">@include('operation.purchase-distribution.actions')</div>
</div>

{{-- 2) تبويب إصدار الفواتير --}}
<div id="tab-issue" class="tab-pane-content d-none">
    @include('operation.purchase-distribution.customer-invoices.header')
    @include('operation.purchase-distribution.customer-invoices.customer-search')
    <div id="contentArea">
        @include('operation.purchase-distribution.customer-invoices.summary')
        @include('operation.purchase-distribution.customer-invoices.distributions-list')
        @include('operation.purchase-distribution.customer-invoices.actions')
    </div>
</div>

{{-- 3) تبويب السجل --}}
<div id="tab-history" class="tab-pane-content d-none">
    @include('operation.purchase-distribution.history.header')
    @include('operation.purchase-distribution.history.header-stats')
    <div class="card border-0 shadow-sm">
        @include('operation.purchase-distribution.history.tabs')
        <div class="card-body">
            @include('operation.purchase-distribution.history.tab-distributions')
            @include('operation.purchase-distribution.history.tab-invoices')
        </div>
    </div>
</div>