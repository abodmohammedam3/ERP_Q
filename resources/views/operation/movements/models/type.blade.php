{{-- نافذة اختيار النوع --}}
<div class="modal fade" id="movementTypeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:14px;">

            <div class="modal-header bg-light border-0" style="border-radius:14px 14px 0 0;">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-tags text-primary me-2"></i> اختيار النوع
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-3">

                <div class="input-group mb-3">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="movementTypeSearchInput"
                           placeholder="اسم النوع"
                           oninput="movementTypeInput(event)"
                           onkeydown="if(event.key==='Enter') searchMovementTypes()">
                </div>

                <div class="table-responsive" style="max-height: 400px;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light sticky-top">
                            <tr class="text-center">
                                <th style="width:70px;">الرقم</th>
                                <th class="text-start">النوع</th>
                            </tr>
                        </thead>
                        <tbody id="movementTypeResults"></tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>

<template id="movementTypeRowTemplate">
    <tr class="movement-type-row" style="cursor:pointer;">
        <td class="c-id text-center"></td>
        <td class="c-name"></td>
    </tr>
</template>