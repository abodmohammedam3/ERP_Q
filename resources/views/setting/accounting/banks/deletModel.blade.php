{{-- نافذة تأكيد حذف البنك --}}
<div class="delete-confirm-overlay" id="deleteConfirmModal">
    <div class="delete-confirm-box">

        <div class="delete-confirm-icon">
            <i class="bi bi-trash3"></i>
        </div>

        <h3>حذف البنك</h3>

        <p>
            هل أنت متأكد من حذف هذا البنك؟
            <br>
            <span>
                لا يمكن التراجع عن هذه العملية بعد تنفيذها.
            </span>
        </p>

        <div class="delete-confirm-actions">

            <button type="button"
                    class="delete-cancel-btn"
                    id="deleteCancelBtn">
                إلغاء
            </button>

            <button type="button"
                    class="delete-confirm-btn"
                    id="deleteConfirmBtn">
                حذف البنك
            </button>

        </div>

    </div>
</div>