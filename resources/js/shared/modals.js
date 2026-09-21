/**
 * Modals Manager - تهيئة وإدارة المودالات العامة
 * يتم تهيئة كل مودال مرة واحدة عند تحميل الصفحة
 */

window.Modals = {
    instances: {},

    // تهيئة مودال معين
    init(modalId, options = {}) {
        const element = document.getElementById(modalId);
        if (!element) {
            console.warn(`⚠️ مودال ${modalId} غير موجود في الصفحة`);
            return null;
        }

        // منع التهيئة المزدوجة
        if (this.instances[modalId]) {
            return this.instances[modalId];
        }

        try {
            const instance = new bootstrap.Modal(element, options);
            this.instances[modalId] = instance;
            console.log(`✅ مودال ${modalId} تم تهيئته بنجاح`);
            return instance;
        } catch (error) {
            console.error(`❌ فشل تهيئة مودال ${modalId}:`, error);
            return null;
        }
    },

    // الحصول على مودال
    get(modalId) {
        return this.instances[modalId] || null;
    },

    // فتح مودال
    show(modalId) {
        const modal = this.get(modalId);
        if (modal) {
            modal.show();
        } else {
            console.warn(`⚠️ مودال ${modalId} غير مهيأ، سيتم محاولة التهيئة`);
            const newModal = this.init(modalId);
            if (newModal) newModal.show();
        }
    },

    // إغلاق مودال
    hide(modalId) {
        const modal = this.get(modalId);
        if (modal) {
            modal.hide();
        } else {
            console.warn(`⚠️ مودال ${modalId} غير مهيأ لإغلاقه`);
        }
    },

    // تهيئة جميع المودالات الموجودة في الصفحة
    initAll() {
        // قائمة بجميع المودالات المتوقعة في النظام
        const modalIds = [
            // المودالات الأساسية
            'SupplierModal',          // المورد (للمشتريات وسندات الصرف)
            'CustomerModal',          // العميل (للمبيعات وسندات القبض)
            'CurrencyModal',          // العملة (لكل الفواتير والسندات)

            // مودالات إضافية (للإصدارات المستقبلية)
            'ItemModal',              // الأصناف
            'WarehouseModal',         // المخازن
            'TypeModal',              // الأنواع

            // مودالات البحث
            'PaymentVoucherSearchModal',   // بحث سندات الصرف
            'ReceiptVoucherSearchModal'    // بحث سندات القبض
        ];

        let initializedCount = 0;
        modalIds.forEach(id => {
            if (document.getElementById(id)) {
                this.init(id);
                initializedCount++;
            }
        });

        console.log(`✅ تم تهيئة ${initializedCount} مودال من أصل ${modalIds.length}`);
        return this;
    },

    // تدمير مودال (إزالته من الذاكرة)
    destroy(modalId) {
        const modal = this.get(modalId);
        if (modal) {
            modal.dispose();
            delete this.instances[modalId];
            console.log(`🗑️ مودال ${modalId} تم تدميره`);
        }
    },

    // إعادة تهيئة جميع المودالات (مفيد بعد تحديث DOM ديناميكي)
    reinitAll() {
        // تدمير جميع المودالات الموجودة
        Object.keys(this.instances).forEach(id => {
            this.destroy(id);
        });
        // إعادة التهيئة
        this.initAll();
    }
};