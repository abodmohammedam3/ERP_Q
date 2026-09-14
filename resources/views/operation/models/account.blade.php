{{-- نافذة اختيار حساب الدفع (صندوق/بنك/شبكة) --}}
<div class="modal fade" id="accountModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="accountModalTitle">
                    <i class="bi bi-wallet2"></i> اختيار الحساب
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <div class="row g-2 mb-3">
                    <div class="col-md-10">
                        <input type="text" class="form-control" id="accountSearchInput"
                               placeholder="اسم الحساب أو الرقم"
                               oninput="accountInput(event)"
                               onkeydown="if(event.key==='Enter') searchAccounts()">
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-primary w-100"
                                onclick="searchAccounts()">بحث</button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr class="text-center">
                                <th>الرقم المحاسبي</th>
                                <th>الاسم</th>
                                <th>اختيار</th>
                            </tr>
                        </thead>
                        <tbody id="accountResults"></tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>

<template id="accountRowTemplate">
    <tr>
        <td class="c-id"></td>
        <td class="c-name"></td>
        <td><button type="button" class="btn btn-sm btn-success select-btn">اختيار</button></td>
    </tr>
</template>