<aside
    class="offcanvas-md offcanvas-end bg-dark text-white d-flex flex-column flex-shrink-0 h-100"
    tabindex="-1"
    id="sidebar"
    aria-labelledby="sidebarLabel"
    style="width: 260px;"
>

    {{-- رأس Sidebar --}}
    <div class="offcanvas-header border-bottom border-secondary px-3 py-3">

        <a
            href="{{ url('/dashboard') }}"
            class="text-decoration-none text-white d-flex align-items-center gap-2"
        >

            <span class="d-flex align-items-center justify-content-center bg-success rounded-2 p-2">
                <i class="bi bi-grid-1x2-fill fs-5"></i>
            </span>

            <span>
                <span class="d-block fw-bold">
                    نظام ERP
                </span>

                <small class="text-white-50">
                    إدارة موارد المؤسسة
                </small>
            </span>

        </a>

        <button
            type="button"
            class="btn-close btn-close-white d-md-none"
            data-bs-dismiss="offcanvas"
            aria-label="إغلاق القائمة">
        </button>

    </div>


    {{-- محتوى Sidebar --}}
    <div class="offcanvas-body d-flex flex-column p-0 overflow-auto">

        <div class="p-3">

            {{-- القسم الرئيسي --}}
            <div class="d-flex align-items-center gap-2 text-white-50 small fw-semibold mb-2 px-2">
                <i class="bi bi-list"></i>
                <span>القائمة الرئيسية</span>
            </div>


            {{-- لوحة التحكم --}}
            <a
                href="{{ url('/dashboard') }}"
                class="d-flex align-items-center gap-3 text-decoration-none rounded-2 px-3 py-2 mb-1
                {{ request()->is('dashboard') ? 'bg-success text-white' : 'text-white' }}"
            >

                <i class="bi bi-speedometer2 fs-5"></i>

                <span class="fw-medium">
                    لوحة التحكم
                </span>

            </a>


            {{-- ================= المحاسبة ================= --}}
            <button
                type="button"
                class="btn btn-dark w-100 d-flex align-items-center justify-content-between text-end rounded-2 px-3 py-2 mb-1 border-0"
                data-bs-toggle="collapse"
                data-bs-target="#accountingMenu"
                aria-expanded="{{ request()->routeIs('chartOfAccounts.*', 'receiptVouchers.*') ? 'true' : 'false' }}"
                aria-controls="accountingMenu"
            >

                <span class="d-flex align-items-center gap-3">

                    <i class="bi bi-calculator fs-5"></i>

                    <span class="fw-medium">
                        المحاسبة
                    </span>

                </span>

                <i class="bi bi-chevron-down small"></i>

            </button>


            <div
                class="collapse {{ request()->routeIs('chartOfAccounts.*', 'receiptVouchers.*') ? 'show' : '' }}"
                id="accountingMenu"
            >

                <div class="border-end border-secondary me-3 pe-2 mb-2">

                    <a
                        href="{{ route('chartOfAccounts.index') }}"
                        class="d-flex align-items-center gap-3 text-decoration-none rounded-2 px-3 py-2
                        {{ request()->routeIs('chartOfAccounts.*') ? 'bg-success text-white' : 'text-white-50' }}"
                    >
                        <i class="bi bi-diagram-3"></i>
                        <span>دليل الحسابات</span>
                    </a>

                    <a
                        href="{{ route('receiptVouchers.index') }}"
                        class="d-flex align-items-center gap-3 text-decoration-none rounded-2 px-3 py-2
                        {{ request()->routeIs('receiptVouchers.*') ? 'bg-success text-white' : 'text-white-50' }}"
                    >
                        <i class="bi bi-cash-stack"></i>
                        <span>سندات القبض</span>
                    </a>

                    <a
                        href="#"
                        class="d-flex align-items-center gap-3 text-white-50 text-decoration-none rounded-2 px-3 py-2"
                    >
                        <i class="bi bi-cash"></i>
                        <span>سندات الصرف</span>
                    </a>

                    <a
                        href="#"
                        class="d-flex align-items-center gap-3 text-white-50 text-decoration-none rounded-2 px-3 py-2"
                    >
                        <i class="bi bi-journal-check"></i>
                        <span>القيود اليومية</span>
                    </a>

                    <a
                        href="#"
                        class="d-flex align-items-center gap-3 text-white-50 text-decoration-none rounded-2 px-3 py-2"
                    >
                        <i class="bi bi-journal-bookmark"></i>
                        <span>الأستاذ العام</span>
                    </a>

                </div>

            </div>


            {{-- ================= المبيعات ================= --}}
            <button
                type="button"
                class="btn btn-dark w-100 d-flex align-items-center justify-content-between text-end rounded-2 px-3 py-2 mb-1 border-0"
                data-bs-toggle="collapse"
                data-bs-target="#salesMenu"
                aria-expanded="{{ request()->routeIs('invoices.*') ? 'true' : 'false' }}"
                aria-controls="salesMenu"
            >

                <span class="d-flex align-items-center gap-3">

                    <i class="bi bi-cart-check fs-5"></i>

                    <span class="fw-medium">
                        المبيعات
                    </span>

                </span>

                <i class="bi bi-chevron-down small"></i>

            </button>


            <div
                class="collapse {{ request()->routeIs('invoices.*') ? 'show' : '' }}"
                id="salesMenu"
            >

                <div class="border-end border-secondary me-3 pe-2 mb-2">

                    <a
                        href="{{ route('invoices.index') }}"
                        class="d-flex align-items-center gap-3 text-decoration-none rounded-2 px-3 py-2
                        {{ request()->routeIs('invoices.*') ? 'bg-success text-white' : 'text-white-50' }}"
                    >
                        <i class="bi bi-receipt"></i>
                        <span>فواتير المبيعات</span>
                    </a>

                    <a
                        href="#"
                        class="d-flex align-items-center gap-3 text-white-50 text-decoration-none rounded-2 px-3 py-2"
                    >
                        <i class="bi bi-arrow-return-right"></i>
                        <span>مردود المبيعات</span>
                    </a>

                    <a
                        href="#"
                        class="d-flex align-items-center gap-3 text-white-50 text-decoration-none rounded-2 px-3 py-2"
                    >
                        <i class="bi bi-people"></i>
                        <span>العملاء</span>
                    </a>

                </div>

            </div>


            {{-- ================= المشتريات ================= --}}
            <button
                type="button"
                class="btn btn-dark w-100 d-flex align-items-center justify-content-between text-end rounded-2 px-3 py-2 mb-1 border-0"
                data-bs-toggle="collapse"
                data-bs-target="#purchasesMenu"
                aria-expanded="{{ request()->routeIs('invoicesPurch.*') ? 'true' : 'false' }}"
                aria-controls="purchasesMenu"
            >

                <span class="d-flex align-items-center gap-3">

                    <i class="bi bi-bag-check fs-5"></i>

                    <span class="fw-medium">
                        المشتريات
                    </span>

                </span>

                <i class="bi bi-chevron-down small"></i>

            </button>


            <div
                class="collapse {{ request()->routeIs('invoicesPurch.*') ? 'show' : '' }}"
                id="purchasesMenu"
            >

                <div class="border-end border-secondary me-3 pe-2 mb-2">

                    <a
                        href="{{ route('invoicesPurch.index') }}"
                        class="d-flex align-items-center gap-3 text-decoration-none rounded-2 px-3 py-2
                        {{ request()->routeIs('invoicesPurch.*') ? 'bg-success text-white' : 'text-white-50' }}"
                    >
                        <i class="bi bi-bag-plus"></i>
                        <span>فواتير المشتريات</span>
                    </a>

                    <a
                        href="#"
                        class="d-flex align-items-center gap-3 text-white-50 text-decoration-none rounded-2 px-3 py-2"
                    >
                        <i class="bi bi-arrow-return-left"></i>
                        <span>مردود المشتريات</span>
                    </a>

                    <a
                        href="#"
                        class="d-flex align-items-center gap-3 text-white-50 text-decoration-none rounded-2 px-3 py-2"
                    >
                        <i class="bi bi-person-lines-fill"></i>
                        <span>الموردون</span>
                    </a>

                </div>

            </div>


            {{-- ================= المخزون ================= --}}
            <a
                href="#"
                class="d-flex align-items-center gap-3 text-white text-decoration-none rounded-2 px-3 py-2 mb-1"
            >
                <i class="bi bi-box-seam fs-5"></i>
                <span class="fw-medium">المخزون</span>
            </a>


            {{-- ================= الأصناف ================= --}}
            <a
                href="#"
                class="d-flex align-items-center gap-3 text-white text-decoration-none rounded-2 px-3 py-2 mb-1"
            >
                <i class="bi bi-boxes fs-5"></i>
                <span class="fw-medium">الأصناف</span>
            </a>


            {{-- ================= التقارير ================= --}}
            <button
                type="button"
                class="btn btn-dark w-100 d-flex align-items-center justify-content-between text-end rounded-2 px-3 py-2 mb-1 border-0"
                data-bs-toggle="collapse"
                data-bs-target="#reportsMenu"
                aria-expanded="false"
                aria-controls="reportsMenu"
            >

                <span class="d-flex align-items-center gap-3">

                    <i class="bi bi-bar-chart-line fs-5"></i>

                    <span class="fw-medium">
                        التقارير
                    </span>

                </span>

                <i class="bi bi-chevron-down small"></i>

            </button>


            <div class="collapse" id="reportsMenu">

                <div class="border-end border-secondary me-3 pe-2">

                    <a
                        href="#"
                        class="d-flex align-items-center gap-3 text-white-50 text-decoration-none rounded-2 px-3 py-2"
                    >
                        <i class="bi bi-box-arrow-up-right"></i>
                        <span>تقرير المخزون</span>
                    </a>

                    <a
                        href="#"
                        class="d-flex align-items-center gap-3 text-white-50 text-decoration-none rounded-2 px-3 py-2"
                    >
                        <i class="bi bi-graph-up-arrow"></i>
                        <span>تقرير المبيعات</span>
                    </a>

                    <a
                        href="#"
                        class="d-flex align-items-center gap-3 text-white-50 text-decoration-none rounded-2 px-3 py-2"
                    >
                        <i class="bi bi-graph-down-arrow"></i>
                        <span>تقرير المشتريات</span>
                    </a>

                </div>

            </div>

        </div>


        {{-- حالة النظام والمستخدم --}}
        <div class="mt-auto border-top border-secondary p-3">

            <div class="d-flex align-items-center gap-2">

                <div class="d-flex align-items-center justify-content-center bg-success rounded-circle p-2">
                    <i class="bi bi-person-fill"></i>
                </div>

                <div class="flex-grow-1">

                    <div class="fw-semibold">
                        م/ عبد الكريم
                    </div>

                    <small class="text-white-50 d-flex align-items-center gap-1">
                        <i class="bi bi-circle-fill text-success small"></i>
                        متصل الآن
                    </small>

                </div>

                <i class="bi bi-three-dots-vertical text-white-50"></i>

            </div>

        </div>

    </div>

</aside>