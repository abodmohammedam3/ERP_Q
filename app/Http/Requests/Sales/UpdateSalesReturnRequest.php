<?php

namespace App\Http\Requests\Sales;

/**
 * التحقق من بيانات تعديل مرتجع البيع.
 *
 * نفس قواعد الإنشاء (كما كان سلوك validateReturn الأصلي
 * يُستخدم للإنشاء والتعديل معًا).
 */
class UpdateSalesReturnRequest extends StoreSalesReturnRequest
{
    // نفس القواعد والرسائل — تُعدّل هنا فقط لو اختلف سلوك التحديث.
}
