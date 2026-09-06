<nav class="navbar navbar-expand-lg navbar-dark bg-dark border-bottom border-secondary shadow-sm px-3 py-2">
    <div class="container-fluid p-0 d-flex justify-content-between align-items-center">

        {{-- القسم الأيسر: زر القائمة الجانبية (للجوال) + هوية النظام --}}
        <div class="d-flex align-items-center gap-2">
            {{-- زر القائمة الجانبية (يظهر للجوال فقط d-lg-none) --}}
            <button type="button" class="btn btn-dark border border-secondary d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar" aria-label="فتح القائمة">
                <i class="bi bi-list fs-4"></i>
            </button>

            {{-- هوية النظام (المربع يمثل الأيقونة، والمستطيل يمثل الاسم) --}}
            <a href="{{ url('/dashboard') }}" class="navbar-brand text-white d-flex align-items-center gap-2 fw-bold mb-0 p-0">
                <span class="d-flex align-items-center justify-content-center bg-success rounded-2 p-2 shadow-sm">
                    <i class="bi bi-grid-1x2-fill"></i>
                </span>
                <span class="d-none d-sm-inline">نظام ERP</span>
            </a>
        </div>

        {{-- القسم الأيمن: زر طي القائمة (للشاشات الكبيرة) + أيقونة الإعدادات --}}
       {{-- القسم الأيمن: زر طي القائمة (للشاشات الكبيرة) + أيقونة الإعدادات --}}
        <div class="d-flex align-items-center gap-2">
            
            {{-- زر طي/فرد القائمة الجانبية (بدون حدود، صغير الحجم) --}}
            <button type="button" class="btn btn-link text-white p-1 d-none d-lg-flex" id="sidebarToggle" aria-label="طي القائمة الجانبية">
                <i class="bi bi-layout-sidebar-inset"></i>
            </button>
        
            {{-- زر الإعدادات (بدون حدود، صغير الحجم) --}}
            <a href="#" class="btn btn-link text-white p-1 d-flex align-items-center justify-content-center" title="الإعدادات">
                <i class="bi bi-gear-fill"></i>
            </a>
        </div>

    </div>
</nav>