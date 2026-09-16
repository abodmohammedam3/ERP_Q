<div class="delete-confirm-overlay" id="deleteBoxModal">

    <div class="delete-confirm-box">

        <div class="delete-confirm-icon">
            <i class="bi bi-trash3"></i>
        </div>

        <h3>حذف الرصيد الافتتاحي</h3>

        <p>
            هل أنت متأكد من حذف هذا الرصيد الافتتاحي؟
            <br>
            <span>
                سيتم حذف الرصيد المرتبط بالحساب من الأرصدة الافتتاحية.
                لا يمكن التراجع.
            </span>
        </p>

        {{-- اسم الحساب الذي سيُحذف (يُعبّأ من الجافاسكربت) --}}
        <div class="delete-confirm-target" id="deleteTargetName"></div>

        {{-- معرّف السطر المراد حذفه --}}
        <input type="hidden" id="deleteId">

        <div class="delete-confirm-actions">

            <button
                type="button"
                class="delete-cancel-btn"
                id="deleteBoxCancelBtn">
                إلغاء
            </button>

            <button
                type="button"
                class="delete-confirm-btn"
                id="deleteBoxConfirmBtn">
                حذف الرصيد
            </button>

        </div>

    </div>

</div>