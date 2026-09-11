{{-- نافذة تأكيد حذف الصنف --}}
<div class="delete-confirm-overlay" id="deleteItemModal">

    <div class="delete-confirm-box">

        <div class="delete-confirm-icon">
            <i class="bi bi-trash3"></i>
        </div>

        <h3>حذف الصنف</h3>

        <p>
            هل أنت متأكد من حذف هذا الصنف؟
            <br>
            <span>
                لا يمكن التراجع عن هذه العملية بعد تنفيذها.
            </span>
        </p>

        <div class="delete-confirm-actions">

            <button type="button"
                    class="delete-cancel-btn"
                    id="deleteItemCancelBtn">
                إلغاء
            </button>

            <button type="button"
                    class="delete-confirm-btn"
                    id="deleteItemConfirmBtn">
                حذف الصنف
            </button>

        </div>

    </div>

</div>