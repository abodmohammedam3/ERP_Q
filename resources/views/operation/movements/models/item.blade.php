{{-- نافذة اختيار الصنف --}}
<div class="modal fade" id="movementItemModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:14px;">

            <div class="modal-header bg-light border-0" style="border-radius:14px 14px 0 0;">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-box-seam text-primary me-2"></i> اختيار الصنف
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-3">

                <div class="input-group mb-3">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="movementItemSearchInput"
                           placeholder="اسم الصنف"
                           oninput="movementItemInput(event)"
                           onkeydown="if(event.key==='Enter') searchMovementItems()">
                </div>

                <div class="table-responsive" style="max-height: 400px;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light sticky-top">
                            <tr class="text-center">
                                <th style="width:70px;">الرقم</th>
                                <th class="text-start">الصنف</th>
                            </tr>
                        </thead>
                        <tbody id="movementItemResults"></tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>

<template id="movementItemRowTemplate">
    <tr class="movement-item-row" style="cursor:pointer;">
        <td class="c-id text-center"></td>
        <td class="c-name"></td>
    </tr>
</template>