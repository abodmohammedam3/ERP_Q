@extends('layouts.app')

@section('title', 'إعدادات النظام')

@section('content')
<div class="container-fluid py-3">

    {{-- العنوان --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">
                <i class="bi bi-gear-fill"></i>
                إعدادات النظام
            </h4>
            <small class="text-muted">
                إدارة النسخ الاحتياطي، الإعدادات العامة، وهوية الشركة
            </small>
        </div>
    </div>

    {{-- التابات --}}
    @include('settings.system.tabs')

    {{-- محتوى التابات --}}
    <div class="tab-content">

        <div class="tab-pane fade show active" id="tab-backup" role="tabpanel">

            @include('settings.system.backup.header')
            @include('settings.system.backup.actions')
            @include('settings.system.backup.table')

        </div>

    </div>

</div>

{{-- القوالب + المودالات --}}
@include('settings.system.backup.templates')
@include('settings.system.backup.modals.export')
@include('settings.system.backup.modals.import')

@endsection

@push('scripts')
    @vite(['resources/js/pages/system-settings.js'])
@endpush
