<!doctype html>
<html lang="ar" dir="rtl">

<head>

    <meta charset="utf-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>
        @yield('title', 'نظام ERP')
    </title>

    <link
        rel="stylesheet"
        href="{{ asset('css/bootstrap.min.css') }}"
    >

    <link
        rel="stylesheet"
        href="{{ asset('font/bootstrap-icons.css') }}"
    >

</head>


<body class="bg-light vh-100 overflow-hidden">

    <div class="d-flex flex-column vh-100">

        {{-- Navbar --}}
        <header class="flex-shrink-0">

            @include('layouts.navbar')

        </header>


        {{-- منطقة النظام --}}
        <div class="d-flex flex-grow-1 overflow-hidden">


            {{-- Sidebar --}}
            @include('layouts.sidebar')


            {{-- المحتوى الرئيسي --}}
            <main
                id="mainContent"
                class="flex-grow-1 overflow-auto p-3 p-md-4"
            >

                @yield('content')

            </main>


        </div>

    </div>


    {{-- Bootstrap --}}
    <script src="{{ asset('js/bootstrap.bundle.min.js') }}"></script>

    {{-- JavaScript الخاص بالنظام --}}
    <script src="{{ asset('js/ricept.js') }}"></script>

</body>

</html>