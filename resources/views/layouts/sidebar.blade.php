<aside
    class="offcanvas-md offcanvas-end bg-dark text-white d-flex flex-column flex-shrink-0 h-100"
    tabindex="-1"
    id="sidebar"
    aria-labelledby="sidebarLabel"
    style="width: 260px;"
>
    {{-- ========================================================= --}}
    {{-- رأس Sidebar --}}
    {{-- ========================================================= --}}
    <div class="offcanvas-header border-bottom border-secondary px-3 py-3">
        <a href="{{ url('/dashboard') }}" class="text-decoration-none text-white d-flex align-items-center gap-2">
            <span class="d-flex align-items-center justify-content-center bg-success rounded-2 p-2">
                <i class="bi bi-grid-1x2-fill fs-5"></i>
            </span>
            <span>
                <span class="d-block fw-bold">نظام ERP</span>
                <small class="text-white-50">إدارة موارد المؤسسة</small>
            </span>
        </a>
        <button type="button" class="btn-close btn-close-white d-md-none" data-bs-dismiss="offcanvas" aria-label="إغلاق القائمة"></button>
    </div>

    {{-- ========================================================= --}}
    {{-- محتوى Sidebar --}}
    {{-- ========================================================= --}}
    <div class="offcanvas-body d-flex flex-column p-0 overflow-auto">
        <div class="p-3">

            {{-- ================================================= --}}
            {{-- الصفحة الرئيسية --}}
            {{-- ================================================= --}}
            <a href="{{ url('/dashboard') }}"
               class="d-flex align-items-center gap-3 text-decoration-none rounded-2 px-3 py-2 mb-1
               {{ request()->is('dashboard') ? 'bg-success text-white' : 'text-white' }}">
                <i class="bi bi-house-door fs-5"></i>
                <span class="fw-medium">الصفحة الرئيسية</span>
            </a>

            {{-- ================================================= --}}
            {{-- الإعدادات --}}
            {{-- ================================================= --}}
            @php
                // تحديد ما إذا كانت أي من قوائم الإعدادات الفرعية نشطة
                $isSettingsActive = request()->routeIs('boxes.*') ||
                                    request()->routeIs('banks.*') ||
                                    request()->routeIs('currenc.*') ||
                                    request()->routeIs('chartOfAccounts.*') ||
                                    request()->routeIs('openingBalances.*') ||
                                    request()->routeIs('suppliers.*') ||
                                    request()->routeIs('customers.*') ||
                                    request()->routeIs('items.*') ||
                                    request()->routeIs('types.*') ||
                                    request()->routeIs('unites.*') ||
                                    request()->routeIs('warehouses.*');
            @endphp
            <button type="button"
                    class="btn btn-dark w-100 d-flex align-items-center justify-content-between text-end rounded-2 px-3 py-2 mb-1 border-0"
                    data-bs-toggle="collapse"
                    data-bs-target="#settingsMenu"
                    aria-expanded="{{ $isSettingsActive ? 'true' : 'false' }}"
                    aria-controls="settingsMenu">
                <span class="d-flex align-items-center gap-3">
                    <i class="bi bi-gear fs-5"></i>
                    <span class="fw-medium">الإعدادات</span>
                </span>
                <i class="bi bi-chevron-down small"></i>
            </button>
            <div class="collapse {{ $isSettingsActive ? 'show' : '' }}" id="settingsMenu">
                <div class="border-end border-secondary me-3 pe-2 mb-2">

                    {{-- إعدادات المحاسبة --}}
                    @php
                        $isAccountingActive = request()->routeIs('boxes.*') ||
                                              request()->routeIs('banks.*') ||
                                              request()->routeIs('currenc.*') ||
                                              request()->routeIs('chartOfAccounts.*') ||
                                              request()->routeIs('openingBalances.*');
                    @endphp
                    <button type="button"
                            class="btn btn-dark w-100 d-flex align-items-center justify-content-between text-end rounded-2 px-3 py-2 border-0"
                            data-bs-toggle="collapse"
                            data-bs-target="#accountingSettingsMenu"
                            aria-expanded="{{ $isAccountingActive ? 'true' : 'false' }}"
                            aria-controls="accountingSettingsMenu">
                        <span class="d-flex align-items-center gap-3">
                            <i class="bi bi-calculator"></i>
                            <span> المحاسبة</span>
                        </span>
                        <i class="bi bi-chevron-down small"></i>
                    </button>
                    <div class="collapse {{ $isAccountingActive ? 'show' : '' }}" id="accountingSettingsMenu">
                        <div class="border-end border-secondary me-3 pe-2">
                            <a href="{{ route('boxes.index') }}"
                               class="d-flex align-items-center gap-3 text-decoration-none rounded-2 px-3 py-2
                               {{ request()->routeIs('boxes.*') ? 'bg-success text-white' : 'text-white-50' }}">
                                <i class="bi bi-diagram-3"></i> <span>الصناديق</span>
                            </a>
                            <a href="{{ route('banks.index') }}"
                               class="d-flex align-items-center gap-3 text-decoration-none rounded-2 px-3 py-2
                               {{ request()->routeIs('banks.*') ? 'bg-success text-white' : 'text-white-50' }}">
                                <i class="bi bi-diagram-3"></i> <span>البنوك</span>
                            </a>
                            <a href="{{ route('currenc.index') }}"
                               class="d-flex align-items-center gap-3 text-decoration-none rounded-2 px-3 py-2
                               {{ request()->routeIs('currenc.*') ? 'bg-success text-white' : 'text-white-50' }}">
                                <i class="bi bi-diagram-3"></i> <span>العملات</span>
                            </a>
                            <a href="{{ route('chartOfAccounts.index') }}"
                               class="d-flex align-items-center gap-3 text-decoration-none rounded-2 px-3 py-2
                               {{ request()->routeIs('chartOfAccounts.*') ? 'bg-success text-white' : 'text-white-50' }}">
                                <i class="bi bi-diagram-3"></i> <span>دليل الحسابات</span>
                            </a>
                            <a href="{{ route('openingBalances.index') }}"
                               class="d-flex align-items-center gap-3 text-decoration-none rounded-2 px-3 py-2
                               {{ request()->routeIs('openingBalances.*') ? 'bg-success text-white' : 'text-white-50' }}">
                                <i class="bi bi-journal-check"></i> <span>الأرصدة الافتتاحية</span>
                            </a>
                        </div>
                    </div>

                    {{--  المشتريات --}}
                    @php
                        $isPurchaseSettingsActive = request()->routeIs('suppliers.*');
                    @endphp
                    <button type="button"
                            class="btn btn-dark w-100 d-flex align-items-center justify-content-between text-end rounded-2 px-3 py-2 border-0"
                            data-bs-toggle="collapse"
                            data-bs-target="#purchaseSettingsMenu"
                            aria-expanded="{{ $isPurchaseSettingsActive ? 'true' : 'false' }}"
                            aria-controls="purchaseSettingsMenu">
                        <span class="d-flex align-items-center gap-3">
                            <i class="bi bi-cart-plus"></i>
                            <span> المشتريات</span>
                        </span>
                        <i class="bi bi-chevron-down small"></i>
                    </button>
                    <div class="collapse {{ $isPurchaseSettingsActive ? 'show' : '' }}" id="purchaseSettingsMenu">
                        <div class="border-end border-secondary me-3 pe-2">
                            <a href="{{ route('suppliers.index') }}"
                               class="d-flex align-items-center gap-3 text-decoration-none rounded-2 px-3 py-2
                               {{ request()->routeIs('suppliers.*') ? 'bg-success text-white' : 'text-white-50' }}">
                                <i class="bi bi-people"></i> <span>الموردين</span>
                            </a>
                        </div>
                    </div>

                    {{-- إعدادات المبيعات --}}
                    @php
                        $isSalesSettingsActive = request()->routeIs('customers.*');
                    @endphp
                    <button type="button"
                            class="btn btn-dark w-100 d-flex align-items-center justify-content-between text-end rounded-2 px-3 py-2 border-0"
                            data-bs-toggle="collapse"
                            data-bs-target="#salesSettingsMenu"
                            aria-expanded="{{ $isSalesSettingsActive ? 'true' : 'false' }}"
                            aria-controls="salesSettingsMenu">
                        <span class="d-flex align-items-center gap-3">
                            <i class="bi bi-cart-check"></i>
                            <span> المبيعات</span>
                        </span>
                        <i class="bi bi-chevron-down small"></i>
                    </button>
                    <div class="collapse {{ $isSalesSettingsActive ? 'show' : '' }}" id="salesSettingsMenu">
                        <div class="border-end border-secondary me-3 pe-2">
                            <a href="{{ route('customers.index') }}"
                               class="d-flex align-items-center gap-3 text-decoration-none rounded-2 px-3 py-2
                               {{ request()->routeIs('customers.*') ? 'bg-success text-white' : 'text-white-50' }}">
                                <i class="bi bi-people"></i> <span>العملاء</span>
                            </a>
                        </div>
                    </div>

                    {{-- إعدادات المخزون --}}
                    @php
                        $isInventorySettingsActive = request()->routeIs('items.*') ||
                                                     request()->routeIs('types.*') ||
                                                     request()->routeIs('unites.*') ||
                                                     request()->routeIs('warehouses.*');
                    @endphp
                    <button type="button"
                            class="btn btn-dark w-100 d-flex align-items-center justify-content-between text-end rounded-2 px-3 py-2 border-0"
                            data-bs-toggle="collapse"
                            data-bs-target="#inventorySettingsMenu"
                            aria-expanded="{{ $isInventorySettingsActive ? 'true' : 'false' }}"
                            aria-controls="inventorySettingsMenu">
                        <span class="d-flex align-items-center gap-3">
                            <i class="bi bi-box-seam"></i>
                            <span> المخزون</span>
                        </span>
                        <i class="bi bi-chevron-down small"></i>
                    </button>
                    <div class="collapse {{ $isInventorySettingsActive ? 'show' : '' }}" id="inventorySettingsMenu">
                        <div class="border-end border-secondary me-3 pe-2">
                            <a href="{{ route('items.index') }}"
                               class="d-flex align-items-center gap-3 text-decoration-none rounded-2 px-3 py-2
                               {{ request()->routeIs('items.*') ? 'bg-success text-white' : 'text-white-50' }}">
                                <i class="bi bi-box"></i> <span>الأصناف</span>
                            </a>
                            <a href="{{ route('types.index') }}"
                               class="d-flex align-items-center gap-3 text-decoration-none rounded-2 px-3 py-2
                               {{ request()->routeIs('types.*') ? 'bg-success text-white' : 'text-white-50' }}">
                                <i class="bi bi-upc-scan"></i> <span>الانواع</span>
                            </a>
                            <a href="{{ route('unites.index') }}"
                               class="d-flex align-items-center gap-3 text-decoration-none rounded-2 px-3 py-2
                               {{ request()->routeIs('unites.*') ? 'bg-success text-white' : 'text-white-50' }}">
                                <i class="bi bi-rulers"></i> <span>الوحدات</span>
                            </a>
                            <a href="{{ route('warehouses.index') }}"
                               class="d-flex align-items-center gap-3 text-decoration-none rounded-2 px-3 py-2
                               {{ request()->routeIs('warehouses.*') ? 'bg-success text-white' : 'text-white-50' }}">
                                <i class="bi bi-rulers"></i> <span>المخازن</span>
                            </a>
                        </div>
                    </div>

                </div>
            </div>

            {{-- ========================================================= --}}
            {{-- العمليات --}}
            {{-- ========================================================= --}}
            @php
                $isOperationsActive = request()->routeIs('invoicesPurch.*') ||
                                      request()->routeIs('sales.*') ||
                                      request()->routeIs('paymentVouchers.*') ||
                                      request()->routeIs('receiptVouchers.*');
            @endphp
            <button type="button"
                    class="btn btn-dark w-100 d-flex align-items-center justify-content-between text-end rounded-2 px-3 py-2 mb-1 border-0"
                    data-bs-toggle="collapse"
                    data-bs-target="#operationsMenu"
                    aria-expanded="{{ $isOperationsActive ? 'true' : 'false' }}"
                    aria-controls="operationsMenu">
                <span class="d-flex align-items-center gap-3">
                    <i class="bi bi-list-check fs-5"></i>
                    <span class="fw-medium">العمليات</span>
                </span>
                <i class="bi bi-chevron-down small"></i>
            </button>
            <div class="collapse {{ $isOperationsActive ? 'show' : '' }}" id="operationsMenu">
                <div class="border-end border-secondary me-3 pe-2 mb-2">

                    {{-- المشتريات --}}
                    @php
                        $isPurchasesActive = request()->routeIs('invoicesPurch.*');
                    @endphp
                    <button type="button"
                            class="btn btn-dark w-100 d-flex align-items-center justify-content-between text-end rounded-2 px-3 py-2 border-0"
                            data-bs-toggle="collapse"
                            data-bs-target="#purchasesMenu"
                            aria-expanded="{{ $isPurchasesActive ? 'true' : 'false' }}"
                            aria-controls="purchasesMenu">
                        <span class="d-flex align-items-center gap-3">
                            <i class="bi bi-cart-plus"></i>
                            <span>المشتريات</span>
                        </span>
                        <i class="bi bi-chevron-down small"></i>
                    </button>
                    <div class="collapse {{ $isPurchasesActive ? 'show' : '' }}" id="purchasesMenu">
                        <div class="border-end border-secondary me-3 pe-2">
                            <a href="{{ route('invoicesPurch.index') }}"
                               class="d-flex align-items-center gap-3 text-decoration-none rounded-2 px-3 py-2
                               {{ request()->routeIs('invoicesPurch.*') ? 'bg-success text-white' : 'text-white-50' }}">
                                <i class="bi bi-receipt"></i> <span>فواتير الشراء</span>
                            </a>
                            <a href="#" class="d-flex align-items-center gap-3 text-decoration-none rounded-2 px-3 py-2 text-white-50">
                                <i class="bi bi-arrow-return-right"></i> <span>مردود المشتريات</span>
                            </a>
                        </div>
                    </div>

                    {{-- المبيعات --}}
                    @php
                        $isSalesActive = request()->routeIs('sales.*');
                    @endphp
                    <button type="button"
                            class="btn btn-dark w-100 d-flex align-items-center justify-content-between text-end rounded-2 px-3 py-2 border-0"
                            data-bs-toggle="collapse"
                            data-bs-target="#salesMenu"
                            aria-expanded="{{ $isSalesActive ? 'true' : 'false' }}"
                            aria-controls="salesMenu">
                        <span class="d-flex align-items-center gap-3">
                            <i class="bi bi-cart-check"></i>
                            <span>المبيعات</span>
                        </span>
                        <i class="bi bi-chevron-down small"></i>
                    </button>
                    <div class="collapse {{ $isSalesActive ? 'show' : '' }}" id="salesMenu">
                        <div class="border-end border-secondary me-3 pe-2">
                            <a href="{{ route('sales.index') }}"
                               class="d-flex align-items-center gap-3 text-decoration-none rounded-2 px-3 py-2
                               {{ request()->routeIs('sales.*') ? 'bg-success text-white' : 'text-white-50' }}">
                                <i class="bi bi-receipt-cutoff"></i> <span>فواتير البيع</span>
                            </a>
                            <a href="#" class="d-flex align-items-center gap-3 text-decoration-none rounded-2 px-3 py-2 text-white-50">
                                <i class="bi bi-arrow-return-left"></i> <span>مردود المبيعات</span>
                            </a>
                        </div>
                    </div>

                    {{-- الحسابات --}}
                    @php
                        $isAccountsActive = request()->routeIs('paymentVouchers.*') ||
                                             request()->routeIs('receiptVouchers.*');
                    @endphp
                    <button type="button"
                            class="btn btn-dark w-100 d-flex align-items-center justify-content-between text-end rounded-2 px-3 py-2 border-0"
                            data-bs-toggle="collapse"
                            data-bs-target="#accountsOperationsMenu"
                            aria-expanded="{{ $isAccountsActive ? 'true' : 'false' }}"
                            aria-controls="accountsOperationsMenu">
                        <span class="d-flex align-items-center gap-3">
                            <i class="bi bi-calculator"></i>
                            <span>الحسابات</span>
                        </span>
                        <i class="bi bi-chevron-down small"></i>
                    </button>
                    <div class="collapse {{ $isAccountsActive ? 'show' : '' }}" id="accountsOperationsMenu">
                        <div class="border-end border-secondary me-3 pe-2">
                            <a href="{{ route('paymentVouchers.index') }}"
                               class="d-flex align-items-center gap-3 text-decoration-none rounded-2 px-3 py-2
                               {{ request()->routeIs('paymentVouchers.*') ? 'bg-success text-white' : 'text-white-50' }}">
                                <i class="bi bi-cash"></i> <span>سند صرف</span>
                            </a>
                            <a href="{{ route('receiptVouchers.index') }}"
                               class="d-flex align-items-center gap-3 text-decoration-none rounded-2 px-3 py-2
                               {{ request()->routeIs('receiptVouchers.*') ? 'bg-success text-white' : 'text-white-50' }}">
                                <i class="bi bi-cash-stack"></i> <span>سند قبض</span>
                            </a>
                        </div>
                    </div>

                    {{-- حركة المخزون --}}
                     <a
                            href="{{ route('movements.index') }}"
                            class="d-flex align-items-center gap-3 text-decoration-none rounded-2 px-3 py-2
                            {{ request()->routeIs('movements.*')
                                ? 'bg-success text-white'
                                : 'text-white-50' }}"
                        >
                    <i class="bi bi-boxes"></i>
                    <span>
                        حركة المخزون
                    </span>
                </a>

                </div>
            </div>

        </div>
    </div>
</aside>