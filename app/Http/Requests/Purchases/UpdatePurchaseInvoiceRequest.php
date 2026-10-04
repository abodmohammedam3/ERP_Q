<?php

namespace App\Http\Requests\Purchases;

/**
 * التحقق من بيانات تعديل فاتورة الشراء.
 *
 * نفس قواعد الإنشاء (كما كان سلوك validateInvoice الأصلي
 * يُستخدم للإنشاء والتعديل معًا).
 */
class UpdatePurchaseInvoiceRequest extends StorePurchaseInvoiceRequest
{
    // نفس القواعد والرسائل — تُعدّل هنا فقط لو اختلف سلوك التحديث.
}