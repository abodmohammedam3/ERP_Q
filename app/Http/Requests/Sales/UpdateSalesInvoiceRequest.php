<?php

namespace App\Http\Requests\Sales;

/**
 * التحقق من بيانات تعديل فاتورة البيع.
 *
 * نفس قواعد الإنشاء (كما كان سلوك validateInvoice الأصلي
 * يُستخدم للإنشاء والتعديل معًا).
 */
class UpdateSalesInvoiceRequest extends StoreSalesInvoiceRequest
{
    // نفس القواعد والرسائل — تُعدّل هنا فقط لو اختلف سلوك التحديث.
}