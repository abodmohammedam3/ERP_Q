<!doctype html>
<html lang="ar" dir="rtl">

<head>

    <meta charset="utf-8">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1" >

    <title>
        @yield('title', 'نظام ERP')
    </title>
    {{-- Bootstrap CSS --}}
    <link
        rel="stylesheet"
        href="{{ asset('css/bootstrap.min.css') }}"  >
    <link
        rel="stylesheet"
        href="{{ asset('css/accounting/chart.css') }}"  >

    <link rel="stylesheet" href="{{ asset('css/accounting/shared-tables.css') }}"  >

    {{-- Bootstrap Icons --}}
    <link
        rel="stylesheet"
        href="{{ asset('css/bootstrap-icons.css') }}" >

</head>


<body class="bg-light vh-100 overflow-hidden">

    <div class="d-flex flex-column vh-100">


        {{-- =====================================================
             Navbar
        ====================================================== --}}

        <header class="flex-shrink-0">

            @include('layouts.navbar')

        </header>



        {{-- =====================================================
             منطقة النظام
        ====================================================== --}}

        <div class="d-flex flex-grow-1 overflow-hidden">


            {{-- =================================================
                 Sidebar
            ================================================== --}}

            @include('layouts.sidebar')



            {{-- =================================================
                 المحتوى الرئيسي
            ================================================== --}}

            <main
                id="mainContent"
                class="flex-grow-1 overflow-auto p-3 p-md-4"
            >

                @yield('content')

            </main>

        </div>

    </div>

{{-- =========================================================
     Bootstrap Toast - أعلى الصفحة في المنتصف
========================================================= --}}

<div
    class="toast-container position-fixed top-0 start-50 translate-middle-x p-3"
    style="z-index: 11000;"
>
    <div
        id="systemToast"
        class="toast border-0 shadow"
        role="alert"
        aria-live="assertive"
        aria-atomic="true"
    >

        <div class="toast-body d-flex align-items-center">

            <i
                id="systemToastIcon"
                class="bi bi-check-circle-fill fs-5 me-2 text-white"
            ></i>

            <span id="systemToastMessage">
                تمت العملية بنجاح
            </span>

            <button
                type="button"
                class="btn-close btn-close-white me-auto"
                data-bs-dismiss="toast"
                aria-label="إغلاق"
            ></button>

        </div>

    </div>
</div>
    {{-- =========================================================
         Bootstrap JavaScript
    ========================================================== --}}

    <script src="{{ asset('js/bootstrap.bundle.min.js') }}"></script>


    {{-- =========================================================
         JavaScript العام للنظام
    ========================================================== --}}

    <script src="{{ asset('js/system.js') }}"></script>


    {{-- =========================================================
         JavaScript الخاص بالصفحة
    ========================================================== --}}

    @stack('scripts')
    @stack('modals')

</body>
</html>