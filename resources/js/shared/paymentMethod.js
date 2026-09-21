/**
 * Payment Method Module - إدارة طريقة الدفع (نقد، بنك، شبكة)
 * هذه النسخة مبسطة وخالية من الأخطاء
 */

window.PaymentMethod = {
    // المعرفات الافتراضية
    methodSelectId: 'PaymentMethod',
    containers: {
        cash: 'cashAccountContainer',
        bank: 'bankAccountContainer',
        network: 'walletAccountContainer'
    },
    isDisabled: false,
    _initialized: false,

    // ========== التهيئة ==========
    init: function(methodSelectId, containers) {
        if (this._initialized) return this;

        // تعيين المعرفات
        if (methodSelectId) this.methodSelectId = methodSelectId;
        if (containers) {
            this.containers = { ...this.containers, ...containers };
        }

        // ربط حدث التغيير بالـ select
        const select = document.getElementById(this.methodSelectId);
        if (select) {
            // إزالة أي مستمعين سابقين لتجنب التكرار
            select.removeEventListener('change', this._handleChange);
            // إضافة المستمع الجديد
            this._handleChange = this.change.bind(this);
            select.addEventListener('change', this._handleChange);
            console.log('✅ تم ربط حدث تغيير طريقة الدفع');
        } else {
            console.warn(`⚠️ عنصر select بالمعرف "${this.methodSelectId}" غير موجود`);
        }

        this._initialized = true;

        // تطبيق الحالة الأولية (تعطيل الحقول)
        this.setDisabled(true);
        this.change(); // تطبيق الإخفاء/الإظهار الأولي

        return this;
    },

    // ========== تعطيل/تفعيل حقول الحسابات ==========
    setDisabled: function(disabled) {
        this.isDisabled = disabled;
        Object.values(this.containers).forEach(function(containerId) {
            const container = document.getElementById(containerId);
            if (container) {
                const inputs = container.querySelectorAll('input, select');
                inputs.forEach(function(el) {
                    el.disabled = disabled;
                });
            }
        });
    },

    // ========== تغيير طريقة الدفع ==========
    change: function() {
        // 1. إخفاء جميع الحاويات
        Object.values(this.containers).forEach(function(id) {
            const el = document.getElementById(id);
            if (el) el.classList.add('d-none');
        });

        // 2. الحصول على طريقة الدفع المختارة
        const select = document.getElementById(this.methodSelectId);
        if (!select) return;
        const method = select.value;

        // 3. إذا كانت الطريقة "أجل" أو فارغة، لا نعرض أي حاوية
        if (!method || method === 'credit') return;

        // 4. إظهار الحاوية المناسبة
        const containerId = this.containers[method];
        if (containerId) {
            const container = document.getElementById(containerId);
            if (container) {
                container.classList.remove('d-none');
                // تطبيق حالة التعطيل على الحقول داخل الحاوية
                const inputs = container.querySelectorAll('input, select');
                inputs.forEach(function(el) {
                    el.disabled = this.isDisabled;
                }, this);
            }
        }
    },

    // ========== الحصول على طريقة الدفع الحالية ==========
    getMethod: function() {
        const select = document.getElementById(this.methodSelectId);
        return select ? select.value : '';
    }
};