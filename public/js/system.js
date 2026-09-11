/**
 * =========================================================
 * System.js
 * الوظائف العامة لنظام ERP
 * =========================================================
 *
 * يحتوي على الوظائف المشتركة بين جميع صفحات النظام.
 *
 * حاليًا:
 * - Bootstrap Toast
 *
 * الاستخدام:
 *
 * showSystemToast('تم الحفظ بنجاح', 'success');
 * showSystemToast('حدث خطأ', 'danger');
 * showSystemToast('تنبيه', 'warning');
 * showSystemToast('معلومة', 'info');
 *
 * =========================================================
 */


function showSystemToast(
    message,
    type = 'success'
) {

    // =====================================================
    // عناصر Toast
    // =====================================================

    const toastElement =
        document.getElementById('systemToast');

    const messageElement =
        document.getElementById('systemToastMessage');

    const iconElement =
        document.getElementById('systemToastIcon');


    // =====================================================
    // التأكد من وجود Toast
    // =====================================================

    if (!toastElement) {

        console.error(
            'لم يتم العثور على systemToast'
        );

        return;
    }


    // =====================================================
    // وضع الرسالة
    // =====================================================

    if (messageElement) {

        messageElement.textContent =
            message;
    }


    // =====================================================
    // إزالة الألوان السابقة
    // =====================================================

    toastElement.classList.remove(
        'bg-success',
        'bg-danger',
        'bg-warning',
        'bg-info',
        'text-white',
        'text-dark'
    );


    // =====================================================
    // نجاح
    // =====================================================

    if (type === 'success') {

        toastElement.classList.add(
            'bg-success',
            'text-white'
        );


        if (iconElement) {

            iconElement.className =
                'bi bi-check-circle-fill fs-5 me-2 text-white';
        }
    }


    // =====================================================
    // خطأ
    // =====================================================

    else if (type === 'danger') {

        toastElement.classList.add(
            'bg-danger',
            'text-white'
        );


        if (iconElement) {

            iconElement.className =
                'bi bi-exclamation-circle-fill fs-5 me-2 text-white';
        }
    }


    // =====================================================
    // تحذير
    // =====================================================

    else if (type === 'warning') {

        toastElement.classList.add(
            'bg-warning',
            'text-dark'
        );


        if (iconElement) {

            iconElement.className =
                'bi bi-exclamation-triangle-fill fs-5 me-2 text-dark';
        }
    }


    // =====================================================
    // معلومات
    // =====================================================

    else if (type === 'info') {

        toastElement.classList.add(
            'bg-info',
            'text-dark'
        );


        if (iconElement) {

            iconElement.className =
                'bi bi-info-circle-fill fs-5 me-2 text-dark';
        }
    }


    // =====================================================
    // تشغيل Bootstrap Toast
    // =====================================================

    const toast =
        bootstrap.Toast.getOrCreateInstance(
            toastElement,
            {
                delay: 3000
            }
        );


    toast.show();
}
/* =========================================================
   السيدبار — فتح القائمة النشطة تلقائياً حسب الرابط
   (بدون حفظ دائم — يفتح فقط عند زيارة الصفحة)
========================================================= */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {

        const currentUrl = window.location.href.split('?')[0].replace(/\/$/, '');
        const allLinks = document.querySelectorAll('.offcanvas-body a[href]');

        allLinks.forEach(function (link) {
            const linkUrl = link.href.split('?')[0].replace(/\/$/, '');

            // إذا تطابق الرابط الحالي مع رابط القائمة
            if (linkUrl === currentUrl) {

                // علّم الرابط كـ active
                link.classList.add('active');

                // افتح كل الـ collapse الأب
                let parent = link.closest('.collapse');
                while (parent) {
                    parent.classList.add('show');

                    const toggleBtn = document.querySelector(
                        '[data-bs-target="#' + parent.id + '"]'
                    );
                    if (toggleBtn) {
                        toggleBtn.setAttribute('aria-expanded', 'true');
                        toggleBtn.classList.remove('collapsed');
                    }

                    parent = parent.parentElement?.closest('.collapse');
                }
            }
        });

    });
})();