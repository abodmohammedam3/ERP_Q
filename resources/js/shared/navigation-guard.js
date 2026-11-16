/* =========================================================================
   Navigation Guard — حارس التنقّل عند وجود بيانات غير محفوظة
   -------------------------------------------------------------------------
   السيناريو المعتمد:
   - المستخدم يدخل/يعدّل في فاتورة شراء أو مرتجع شراء
   - ينقر أي رابط في القائمة الجانبية
   - يظهر مودال: [متابعة التحرير] [تجاهل والمغادرة]
     * متابعة التحرير  → يبقى في الشاشة، لا شيء يضيع
     * تجاهل والمغادرة → يغادر باختياره
   - إغلاق التبويب / إعادة التحميل → beforeunload (تحذير المتصفح)
   - Ctrl+Click / الزر الأوسط → تبويب جديد، التبويب الأصلي يبقى سليماً

   الاستخدام من شاشة (سطر واحد داخل ملف الشاشة):
       NavigationGuard.register(() => isSavingInvoice ? false
           : (invoiceMode !== 'view' && takeSnapshot() !== editSnapshot));

   مخاطر موثّقة (قرار معتمد):
   - زر الرجوع (popstate): لا يُعالج عمداً — خيار (أ) من الخطة.
   - شاشات بلا لقطة (Snapshot) قد تُظهر المودال في وضع التعديل
     دون تعديل فعلي — يُحل في المرحلة 2 بـ takeReturnSnapshot().
   ========================================================================= */

(function () {
    'use strict';

    const NavigationGuard = {

        /** فاحصات "الاتساخ" المسجّلة من الشاشات */
        _checkers: [],

        /** راية one-shot: تخطَّ الفحص للتنقّل البرمجي القادم فقط (المرحلة 2) */
        _silenced: false,

        message: 'لديك بيانات غير محفوظة. هل تريد المغادرة وتجاهل التغييرات؟',

        /** تسجيل فاحص: دالة تعيد true إذا كانت الشاشة فيها بيانات غير محفوظة */
        register(checkerFn) {
            if (typeof checkerFn === 'function') {
                this._checkers.push(checkerFn);
            }
            return this;
        },

        /** هل توجد بيانات غير محفوظة في أي شاشة مسجّلة؟ */
        isDirty() {
            if (this._silenced) {
                this._silenced = false;
                return false;
            }
            return this._checkers.some(fn => {
                try {
                    return !!fn();
                } catch (e) {
                    // فاحص معطوب لا يعلق المستخدم — يعامَل كـ "غير متسخ"
                    console.warn('[NavigationGuard] checker error:', e);
                    return false;
                }
            });
        },

        /** تخطَّ التحذير مرة واحدة (لاستخدام مستقبلي قبل redirect برمجي) */
        silence() {
            this._silenced = true;
        },

        /** هل النقرة تنوي فتح تبويب جديد أو ليست نقرة يسار عادية؟ */
        _isAuxiliaryClick(e) {
            return e.ctrlKey || e.metaKey || e.shiftKey || e.altKey
                || (e.button !== undefined && e.button !== 0);
        },

        /** هل الرابط يجب استثناؤه من الاعتراض؟ */
        _isExemptLink(a) {
            const href = a.getAttribute('href');

            if (!href) return true;
            if (href === '#' || href.startsWith('javascript:')) return true;
            if (a.target === '_blank') return true;
            if (a.hasAttribute('download')) return true;

            // نفس الصفحة (بدون تغيير مسار) — لا مغادرة فعلية
            try {
                const url = new URL(a.href, window.location.origin);
                return url.pathname === window.location.pathname;
            } catch (e) {
                return true;
            }
        },

        init() {
            // ── 1) اعتراض نقرات روابط التنقّل ──
            // مرحلة الالتقاط (capture) = أول من يقرر قبل معالجات السيدبار/الشاشة
            document.addEventListener('click', (e) => {

                // Ctrl+Click / Meta+Click / الزر الأوسط → تبويب جديد للمتصفح
                if (this._isAuxiliaryClick(e)) return;

                const a = e.target.closest('a[href]');
                if (!a || this._isExemptLink(a)) return;

                if (!this.isDirty()) return;

                // أوقف التنقّل وكل معالجات لاحقة، واعرض المودال
                e.preventDefault();
                e.stopPropagation();

                window.appConfirm(this.message, {
                    title: '⚠️ بيانات غير محفوظة',
                    okText: 'تجاهل والمغادرة',
                    cancelText: 'متابعة التحرير',
                    danger: true,
                }).then((leave) => {
                    if (leave) {
                        this._checkers = [];   // مغادرة باختيار واعٍ
                        this.silence();        // ✅ لا مربع متصفح بعد قرار المستخدم
                        window.location.href = a.href;
                    }
                    // "متابعة التحرير" → لا شيء: المستخدم بقي في شاشته
                });
            }, true);

            // ── 2) إغلاق تبويب / إعادة تحميل / لصق رابط ──
            // نفس منطق payment.js (س1609–1620) — مركزي الآن
            window.addEventListener('beforeunload', (e) => {
                if (!this.isDirty()) return;
                e.preventDefault();
                e.returnValue = ''; // مطلوب لـ Chrome
            });
        },
    };

    window.NavigationGuard = NavigationGuard;

    // التهيئة الفورية: إضافة المستمعين لا تتطلب جاهزية DOM،
    // والفاحصات تُسجّل من حزم الصفحات لاحقاً بلا تأثير
    NavigationGuard.init();
})();
