<?php

namespace App\Http\Requests\Purchases;

/**
 * التحقق من بيانات تعديل مرتجع الشراء.
 *
 * نفس قواعد الإنشاء (كما كان سلوك validateReturn الأصلي
 * يُستخدم للإنشاء والتعديل معًا).
 */
class UpdatePurchaseReturnRequest extends StorePurchaseReturnRequest
{
    // نفس القواعد والرسائل — تُعدّل هنا فقط لو اختلف سلوك التحديث.
}
