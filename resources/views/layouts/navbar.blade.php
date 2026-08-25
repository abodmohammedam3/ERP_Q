<nav class="navbar navbar-expand bg-dark text-white border-bottom border-secondary shadow-sm px-2 px-md-4 py-2">

    <div class="container-fluid p-0">

        {{-- زر Sidebar --}}
        <button
            type="button"
            class="btn btn-dark border border-secondary d-md-none me-2"
            data-bs-toggle="offcanvas"
            data-bs-target="#sidebar"
            aria-controls="sidebar"
            aria-label="فتح القائمة"
        >
            <i class="bi bi-list fs-4"></i>
        </button>


        {{-- هوية النظام --}}
        <a
            href="{{ url('/dashboard') }}"
            class="navbar-brand text-white d-flex align-items-center gap-2 fw-bold mb-0"
        >

            <span class="d-flex align-items-center justify-content-center bg-success rounded-2 p-2">
                <i class="bi bi-grid-1x2-fill"></i>
            </span>

            <span class="d-none d-sm-inline">
                نظام ERP
            </span>

        </a>


        {{-- عناصر Navbar --}}
        <div class="d-flex align-items-center gap-2 ms-auto">

            {{-- بحث سطح المكتب --}}
            <div class="d-none d-lg-block">

                <div class="input-group">

                    <span class="input-group-text bg-secondary bg-opacity-25 text-white border-secondary">
                        <i class="bi bi-search"></i>
                    </span>

                    <input
                        type="search"
                        class="form-control bg-secondary bg-opacity-25 text-white border-secondary"
                        placeholder="بحث سريع..."
                        aria-label="بحث سريع"
                    >

                </div>

            </div>


            {{-- بحث الهاتف --}}
            <button
                type="button"
                class="btn btn-dark border border-secondary d-lg-none"
                aria-label="بحث"
            >
                <i class="bi bi-search"></i>
            </button>


            {{-- الإشعارات --}}
            <div class="dropdown">

                <button
                    type="button"
                    class="btn btn-dark border border-secondary position-relative"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                    aria-label="الإشعارات"
                >

                    <i class="bi bi-bell fs-5"></i>

                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        3
                    </span>

                </button>


                <ul class="dropdown-menu dropdown-menu-end text-end shadow border-0 mt-2">

                    <li>
                        <h6 class="dropdown-header fw-bold">
                            الإشعارات
                        </h6>
                    </li>

                    <li>
                        <a href="#" class="dropdown-item d-flex align-items-center gap-2 py-2">
                            <i class="bi bi-receipt text-primary"></i>
                            <span>تم إضافة فاتورة مبيعات جديدة</span>
                        </a>
                    </li>

                    <li>
                        <a href="#" class="dropdown-item d-flex align-items-center gap-2 py-2">
                            <i class="bi bi-exclamation-triangle text-warning"></i>
                            <span>تنبيه: نقص في المخزون</span>
                        </a>
                    </li>

                    <li>
                        <a href="#" class="dropdown-item d-flex align-items-center gap-2 py-2">
                            <i class="bi bi-cash-stack text-success"></i>
                            <span>تم تسجيل سند قبض جديد</span>
                        </a>
                    </li>

                    <li>
                        <hr class="dropdown-divider">
                    </li>

                    <li>
                        <a href="#" class="dropdown-item text-center text-primary fw-semibold">
                            عرض جميع الإشعارات
                        </a>
                    </li>

                </ul>

            </div>


            {{-- المستخدم --}}
            <div class="dropdown">

                <button
                    type="button"
                    class="btn btn-dark border border-secondary d-flex align-items-center gap-2"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                >

                    <span class="d-flex align-items-center justify-content-center bg-success rounded-circle p-2">
                        <i class="bi bi-person-fill"></i>
                    </span>

                    <span class="d-none d-md-inline fw-semibold">
                        م/ عبد الكريم
                    </span>

                    <i class="bi bi-chevron-down small"></i>

                </button>


                <ul class="dropdown-menu dropdown-menu-end text-end shadow border-0 mt-2">

                    <li>
                        <h6 class="dropdown-header fw-bold">
                            الحساب
                        </h6>
                    </li>

                    <li>
                        <a href="#" class="dropdown-item d-flex align-items-center gap-2 py-2">
                            <i class="bi bi-person"></i>
                            <span>الملف الشخصي</span>
                        </a>
                    </li>

                    <li>
                        <a href="#" class="dropdown-item d-flex align-items-center gap-2 py-2">
                            <i class="bi bi-gear"></i>
                            <span>الإعدادات</span>
                        </a>
                    </li>

                    <li>
                        <hr class="dropdown-divider">
                    </li>

                    <li>
                        <a href="#" class="dropdown-item text-danger d-flex align-items-center gap-2 py-2">
                            <i class="bi bi-box-arrow-right"></i>
                            <span>تسجيل الخروج</span>
                        </a>
                    </li>

                </ul>

            </div>

        </div>

    </div>

</nav>