<?php

namespace App\Http\Requests\Inventory;

/**
 * طلب تعديل صنف — نفس قواعد الإنشاء تماماً.
 *
 * لا unique إطلاقاً: التكرار مسموح بقرار Q2.
 */
class ItemUpdateRequest extends ItemStoreRequest
{
    // نفس القواعد والرسائل — تُعدّل هنا فقط لو اختلف سلوك التحديث.
}