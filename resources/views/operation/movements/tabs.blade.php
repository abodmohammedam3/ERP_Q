{{-- ═══════════════════════════════════════════════════════════
     شريط التابات لشاشة حركات المخزون
     ═══════════════════════════════════════════════════════════ --}}

<ul class="nav nav-tabs mb-3" id="movementTabs" role="tablist">

    <li class="nav-item" role="presentation">
        <button
            class="nav-link active"
            type="button"
            data-bs-toggle="tab"
            data-bs-target="#tab-movements"
            data-tab="movements"
            role="tab"
            aria-selected="true"
        >
            <i class="bi bi-arrow-left-right"></i>
            حركات المخزون
        </button>
    </li>

    <li class="nav-item" role="presentation">
        <button
            class="nav-link"
            type="button"
            data-bs-toggle="tab"
            data-bs-target="#tab-balances"
            data-tab="balances"
            role="tab"
            aria-selected="false"
        >
            <i class="bi bi-boxes"></i>
            أرصدة المخزون
        </button>
    </li>

</ul>

{{-- النوع الحالي (يُحدّث من الجافاسكربت) --}}
<input type="hidden" id="activeMovementTab" value="movements">