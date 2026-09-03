<div class="card shadow-sm">
    <div class="card-header bg-white">
        <ul class="nav nav-tabs card-header-tabs">
            <li class="nav-item">
                <button
                    class="nav-link active"
                    data-bs-toggle="tab"
                    data-bs-target="#accounts"
                    type="button"
                >
                    الحسابات
                </button>
            </li>
            <li class="nav-item">
                <button
                    class="nav-link"
                    data-bs-toggle="tab"
                    data-bs-target="#customers"
                    type="button"
                >
                    العملاء
                </button>
            </li>
            <li class="nav-item">
                <button
                    class="nav-link"
                    data-bs-toggle="tab"
                    data-bs-target="#suppliers"
                    type="button"
                >
                    الموردين
                </button>
            </li>
            <li class="nav-item">
                <button
                    class="nav-link"
                    data-bs-toggle="tab"
                    data-bs-target="#cashboxes"
                    type="button"
                >
                    الصناديق
                </button>
            </li>
            <li class="nav-item">
                <button
                    class="nav-link"
                    data-bs-toggle="tab"
                    data-bs-target="#banks"
                    type="button"
                >
                    البنوك
                </button>
            </li>
            {{-- <li class="nav-item">
                <button
                    class="nav-link"
                    data-bs-toggle="tab"
                    data-bs-target="#inventory"
                    type="button"
                >
                    المخزون
                </button>
            </li> --}}
        </ul>
    </div>
    <div class="card-body">
        <div class="tab-content">
            <div
                class="tab-pane fade show active"
                id="accounts"
            >
                @include('setting.accounting.openingBalances.partAccounts')
            </div>
            <div
                class="tab-pane fade"
                id="customers"
            >
                @include('setting.accounting.openingBalances.partCustomer')
            </div>
            <div
                class="tab-pane fade"
                id="suppliers"
            >
                @include('setting.accounting.openingBalances.partSuppliers')
            </div>
            <div
                class="tab-pane fade"
                id="cashboxes"
            >
                @include('setting.accounting.openingBalances.partCashbox')
            </div>
            <div
                class="tab-pane fade"
                id="banks"
            >
                @include('setting.accounting.openingBalances.partBank')
            </div>

            {{-- <div
                class="tab-pane fade"
                id="inventory"
            >
                @include('setting.accounting.openingBalances.partInventory')
            </div> --}}
        </div>
    </div>
</div>