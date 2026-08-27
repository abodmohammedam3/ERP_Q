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

        {{-- عناصر Navbar (تظهر في قائمة الجوال، وتظهر متصلة في الشاشات الكبيرة) --}}
        <div class="collapse navbar-collapse" id="mobileNavbarContent">
            <div class="d-flex flex-column flex-lg-row align-items-stretch align-items-lg-center gap-2 gap-lg-3 mt-3 mt-lg-0 w-100">
                
               

               {{-- الإشعارات --}}
                <div class="dropdown dropstart">   <!-- تم الإضافة -->
                       <button 
                           type="button" 
                           class="btn btn-dark border border-secondary position-relative d-flex align-items-center justify-content-center rounded-circle" 
                           style="width: 40px; height: 40px;"
                           data-bs-toggle="dropdown" 
                           aria-expanded="false" 
                           aria-label="الإشعارات"
                       >
                           <i class="bi bi-bell fs-5"></i>
                           <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-dark" style="font-size: 0.65rem;">
                               3
                           </span>
                       </button>
                    <ul class="dropdown-menu dropdown-menu-end text-end shadow border-0 mt-2">
                        <li>
                            <h6 class="dropdown-header fw-bold">الإشعارات</h6>
                        </li>
                        <li><a href="#" class="dropdown-item d-flex align-items-center gap-2 py-2"><i class="bi bi-receipt text-primary"></i> <span>تم إضافة فاتورة مبيعات جديدة</span></a></li>
                        <li><a href="#" class="dropdown-item d-flex align-items-center gap-2 py-2"><i class="bi bi-exclamation-triangle text-warning"></i> <span>تنبيه: نقص في المخزون</span></a></li>
                        <li><a href="#" class="dropdown-item d-flex align-items-center gap-2 py-2"><i class="bi bi-cash-stack text-success"></i> <span>تم تسجيل سند قبض جديد</span></a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a href="#" class="dropdown-item text-center text-primary fw-semibold">عرض جميع الإشعارات</a></li>
                    </ul>
                </div>

                <!-- {{-- المستخدم --}}
                <div class="dropdown">
                    <button type="button" class="btn btn-dark border border-secondary d-flex align-items-center gap-2 w-100 w-lg-auto justify-content-between justify-content-lg-start" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="d-flex align-items-center justify-content-center bg-success rounded-circle p-2">
                            <i class="bi bi-person-fill"></i>
                        </span>
                        <span class="d-none d-md-inline fw-semibold">م/ عبد الكريم</span>
                        <i class="bi bi-chevron-down small"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end text-end shadow border-0 mt-2">
                        <li><h6 class="dropdown-header fw-bold">الحساب</h6></li>
                        <li><a href="#" class="dropdown-item d-flex align-items-center gap-2 py-2"><i class="bi bi-person"></i> <span>الملف الشخصي</span></a></li>
                        <li><a href="#" class="dropdown-item d-flex align-items-center gap-2 py-2"><i class="bi bi-gear"></i> <span>الإعدادات</span></a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a href="#" class="dropdown-item text-danger d-flex align-items-center gap-2 py-2"><i class="bi bi-box-arrow-right"></i> <span>تسجيل الخروج</span></a></li>
                    </ul>
                </div> -->

            </div>
        </div>

    </div>
</nav>