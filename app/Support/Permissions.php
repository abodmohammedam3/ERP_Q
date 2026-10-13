<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;

/**
 * مساعد الصلاحيات المركزي.
 *
 * 🔓 الوضع الحالي: النظام بلا صلاحيات — كل شيء مسموح.
 * 🔒 عند التفعيل: اضبط PERMISSIONS_ENABLED=true في .env
 *
 * الاستخدام:
 *   Permissions::can('purchase_invoices.create');
 *   Permissions::authorize('purchase_invoices.delete');
 */
class Permissions
{
    /**
     * تحقق من الصلاحية بدون رمي استثناء.
     */
    public static function can(string $permission): bool
    {
        // 🔓 الوضع الحالي: النظام بلا صلاحيات
        if (! config('permissions.enabled', false)) {
            return true;
        }

        // 🔒 عند التفعيل: التحقق من الصلاحية
        return Auth::check() && Auth::user()->can($permission);
    }

    /**
     * تحقق + رمي 403 عند المنع.
     */
    public static function authorize(string $permission): void
    {
        if (! static::can($permission)) {
            abort(403, 'غير مصرّح لك بهذا الإجراء');
        }
    }

    /**
     * اختصار للصلاحيات المتكررة.
     */
    public static function any(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (static::can($permission)) {
                return true;
            }
        }

        return false;
    }
}