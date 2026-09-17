<div class="delete-confirm-overlay" id="deleteBoxModal">

    <div class="delete-confirm-box">

        {{-- الأيقونة --}}
        <div class="delete-confirm-icon">
            <i class="bi bi-trash3"></i>
        </div>

        {{-- العنوان --}}
        <h3>حذف الرصيد الافتتاحي</h3>

        {{-- الرسالة --}}
        <p>
            هل أنت متأكد من حذف هذا الرصيد الافتتاحي؟
            <br>
            <span>
                سيتم حذف الرصيد المرتبط بالحساب من الأرصدة الافتتاحية.
                لا يمكن التراجع.
            </span>
        </p>

        {{-- اسم العنصر (يُعبّأ من الجافاسكربت) --}}
        <div class="delete-confirm-target" id="deleteTargetName"></div>

        {{-- المعرّف --}}
        <input type="hidden" id="deleteId">

        {{-- الأزرار --}}
        <div class="delete-confirm-actions">

            <button type="button"
                    class="delete-cancel-btn"
                    id="deleteBoxCancelBtn">
                إلغاء
            </button>

            <button type="button"
                    class="delete-confirm-btn"
                    id="deleteBoxConfirmBtn">
                حذف الرصيد
            </button>

        </div>

    </div>

</div>