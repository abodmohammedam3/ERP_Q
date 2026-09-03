<nav class="navbar navbar-expand-lg navbar-dark bg-dark border-bottom border-secondary shadow-sm px-3 py-2">
    <div class="container-fluid p-0">

        {{-- زر القائمة الجانبية (يظهر للجوال فقط) --}}
        <button type="button" class="btn btn-dark border border-secondary d-lg-none me-2" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar" aria-label="فتح القائمة">
            <i class="bi bi-list fs-4"></i>
        </button>

        {{-- هوية النظام --}}
        <a href="{{ url('/dashboard') }}" class="navbar-brand text-white d-flex align-items-center gap-2 fw-bold mb-0 me-auto me-lg-0">
            <span class="d-flex align-items-center justify-content-center bg-success rounded-2 p-2 shadow-sm">
                <i class="bi bi-grid-1x2-fill"></i>
            </span>
            <span class="d-none d-sm-inline">نظام ERP</span>
        </a>

        {{-- زر برغر لقائمة الجوال --}}
        <button class="navbar-toggler border-0 d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#mobileNavbarContent" aria-controls="mobileNavbarContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>


    </div>
</nav>