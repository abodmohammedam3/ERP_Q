<div class="card shadow-sm mb-3">

    <div class="card-body">

        <div class="d-flex flex-wrap gap-2">


            {{-- =================================================
                 أمر توريد مخزني
                 ================================================= --}}
            <button
                type="button"
                id="btnAddSupplyMovement"
                class="btn btn-success"
                onclick="startSupplyMovement()"
            >

                <i class="bi bi-box-arrow-in-down"></i>

                أمر توريد مخزني

            </button>


            {{-- =================================================
                 أمر صرف مخزني
                 ================================================= --}}
            <button
                type="button"
                id="btnAddIssueMovement"
                class="btn btn-warning"
                onclick="startIssueMovement()"
            >

                <i class="bi bi-box-arrow-up"></i>

                أمر صرف مخزني

            </button>


            {{-- =================================================
                 طباعة
                 ================================================= --}}
            <button
                type="button"
                id="btnPrintMovement"
                class="btn btn-outline-secondary"
                onclick="printMovement()"
            >

                <i class="bi bi-printer"></i>

                طباعة

            </button>

        </div>

    </div>

</div>