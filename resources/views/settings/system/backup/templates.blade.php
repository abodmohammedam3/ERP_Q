{{-- ═══ صف نسخة ═══ --}}
<template id="sbRowTemplate">
    <tr class="sb-row">
        <td class="sb-filename"></td>
        <td class="text-center sb-type"></td>
        <td class="text-center sb-date"></td>
        <td class="text-center sb-size"></td>
        <td class="text-center">
            <div class="btn-action-group">

                <button type="button"
                        class="btn btn-sm btn-outline-primary sb-btn-download"
                        title="تنزيل">
                    <i class="bi bi-download"></i>
                </button>

                <button type="button"
                        class="btn btn-sm btn-outline-danger sb-btn-delete"
                        title="حذف">
                    <i class="bi bi-trash3"></i>
                </button>

            </div>
        </td>
    </tr>
</template>

{{-- ═══ لا توجد بيانات ═══ --}}
<template id="sbEmptyTemplate">
    <tr>
        <td colspan="5" class="text-center py-5 text-muted">
            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
            لا توجد نسخ احتياطية بعد
            <br>
            <small>اضغط "تصدير نسخة" للبدء</small>
        </td>
    </tr>
</template>

{{-- ═══ حالة خطأ ═══ --}}
<template id="sbErrorTemplate">
    <tr>
        <td colspan="5" class="text-center py-5 text-danger">
            <i class="bi bi-exclamation-triangle fs-2 d-block mb-2"></i>
            فشل تحميل قائمة النسخ
        </td>
    </tr>
</template>

{{-- ═══ شارات النوع ═══ --}}
<template id="sbBadgeManualTemplate">
    <span class="badge bg-primary">يدوي</span>
</template>

<template id="sbBadgeAutoTemplate">
    <span class="badge bg-success">تلقائي</span>
</template>

<template id="sbBadgeSafetyTemplate">
    <span class="badge bg-warning text-dark">آمن</span>
</template>

<template id="sbBadgeUnknownTemplate">
    <span class="badge bg-secondary">—</span>
</template>

{{-- ═══ مودال حذف نسخة ═══ --}}
<div class="delete-confirm-overlay" id="sbDeleteModal">
    <div class="delete-confirm-box">

        <div class="delete-confirm-icon">
            <i class="bi bi-trash3"></i>
        </div>

        <h3>حذف النسخة الاحتياطية</h3>

        <p>
            هل أنت متأكد من حذف هذا الملف؟
            <br>
            <span id="sbDeleteFilename" class="text-muted small"></span>
        </p>

        <div class="delete-confirm-actions">
            <button type="button" class="delete-cancel-btn" id="sbDeleteCancelBtn">
                إلغاء
            </button>
            <button type="button" class="delete-confirm-btn" id="sbDeleteConfirmBtn">
                حذف
            </button>
        </div>

    </div>
</div>

{{-- ═══ مودال تنظيف القديم ═══ --}}
<div class="delete-confirm-overlay" id="sbCleanupModal">
    <div class="delete-confirm-box">

        <div class="delete-confirm-icon">
            <i class="bi bi-eraser"></i>
        </div>

        <h3>تنظيف النسخ القديمة</h3>

        <p>
            سيتم حذف النسخ التي تجاوزت الحد الأقصى.
            <br>
            <span class="text-muted small">
                احتفاظ يدوي: 7 | احتفاظ آمن: 3
            </span>
        </p>

        <div class="delete-confirm-actions">
            <button type="button" class="delete-cancel-btn" id="sbCleanupCancelBtn">
                إلغاء
            </button>
            <button type="button" class="delete-confirm-btn" id="sbCleanupConfirmBtn">
                تنظيف الآن
            </button>
        </div>

    </div>
</div>
