<?php

namespace App\Reports\Contracts;

/**
 * العقد الموحّد لكل تقرير في مركز التقارير.
 *
 * كل تقرير هو class مستقل يعرّف نفسه (مفتاح، عنوان، تصنيف، فلاتر، أعمدة)
 * وينفّذ الاستعلام الخاص به عبر run().
 *
 * إضافة تقرير جديد = class واحد ينفّذ هذا العقد + سطر في ReportRegistry.
 * لا تعديل على Routes ولا Views ولا JavaScript.
 */
interface Report
{
    /**
     * المفتاح الفريد للتقرير (يُستخدم في الروابط: /reports/data/{key}).
     */
    public function key(): string;

    /**
     * عنوان التقرير بالعربية.
     */
    public function title(): string;

    /**
     * وصف قصير يظهر تحت العنوان في القائمة الجانبية.
     */
    public function description(): string;

    /**
     * أيقونة Bootstrap Icons (بدون بادئة bi-).
     * مثال: 'journal-text'
     */
    public function icon(): string;

    /**
     * تصنيف التقرير: accounting | sales | purchases | inventory | vouchers
     */
    public function category(): string;

    /**
     * تعريف الفلاتر (JSON declaration).
     *
     * كل فلتر:
     * [
     *   'key'    => 'date_from',
     *   'label'  => 'من تاريخ',
     *   'type'   => 'date|select|text|number|account|item',
     *   'source' => 'coins'        // مطلوب فقط مع type=select (اسم مصدر البيانات)
     *   'col'    => 'col-md-3',    // عرض العمود في الشبكة
     *   'value'  => '...'          // قيمة افتراضية اختيارية
     * ]
     *
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array;

    /**
     * تعريف الأعمدة (JSON declaration).
     *
     * كل عمود:
     * [
     *   'key'    => 'debit',
     *   'label'  => 'مدين',
     *   'type'   => 'money|date|number|text',   // يحدد التنسيق والمحاذاة
     *   'footer' => 'sum|last|none',            // 'sum' = يُجمع في صف الإجماليات،
     *                                           // 'last' = قيمة آخر صف مرئي (للرصيد في كشف الحساب)
     * ]
     *
     * قد يعدّل التقرير الأعمدة ديناميكياً حسب الفلاتر
     * (يُرجعها run() ضمن meta['columns'] إن غيّرها).
     *
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array;

    /**
     * تنفيذ التقرير.
     *
     * @param array<string, mixed> $filters فلاتر مُتحقّق منها (مفاتيحها فقط ضمن filters())
     * @return array{
     *   rows: array<int, array<string, mixed>>,
     *   totals: array<string, float>,
     *   meta: array<string, mixed>   // اختياري: columns معدّلة، ملاحظات، إلخ
     * }
     */
    public function run(array $filters): array;
}
