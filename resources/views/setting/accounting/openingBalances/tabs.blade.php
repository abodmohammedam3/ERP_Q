<ul class="nav nav-tabs mb-3" id="obTabs" role="tablist">

    <li class="nav-item" role="presentation">
        <button class="nav-link active"
                type="button"
                data-bs-toggle="tab"
                data-bs-target="#tab-cash"
                data-type="CASH"
                role="tab">
            <i class="fas fa-money-bill"></i> الصناديق
        </button>
    </li>

    <li class="nav-item" role="presentation">
        <button class="nav-link"
                type="button"
                data-bs-toggle="tab"
                data-bs-target="#tab-bank"
                data-type="BANK"
                role="tab">
            <i class="fas fa-university"></i> البنوك
        </button>
    </li>

    <li class="nav-item" role="presentation">
        <button class="nav-link"
                type="button"
                data-bs-toggle="tab"
                data-bs-target="#tab-customer"
                data-type="CUSTOMER"
                role="tab">
            <i class="fas fa-users"></i> العملاء
        </button>
    </li>

    <li class="nav-item" role="presentation">
        <button class="nav-link"
                type="button"
                data-bs-toggle="tab"
                data-bs-target="#tab-supplier"
                data-type="SUPPLIER"
                role="tab">
            <i class="fas fa-truck"></i> الموردين
        </button>
    </li>

</ul>

{{-- النوع الحالي (يُحدّث من الجافاسكربت) --}}
<input type="hidden" id="currentType" value="CASH">