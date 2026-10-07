<?php

namespace App\Reports;

use App\Reports\Contracts\Report;
use App\Reports\Accounting\TrialBalanceReport;
use App\Reports\Accounting\GeneralLedgerReport;
use App\Reports\Accounting\AccountStatementReport;
use App\Reports\Vouchers\VouchersReport;
use App\Reports\Inventory\ItemLedgerReport;
use App\Reports\Sales\SalesInvoicesReport;
use App\Reports\Purchases\PurchaseInvoicesReport;
use InvalidArgumentException;

/**
 * سجل التقارير — "دليل" كل التقارير في النظام.
 *
 * التسجيل هنا فقط هو ما يظهر التقرير في مركز التقارير.
 * إضافة تقرير = class ينفّذ عقد Report + سطر في REGISTRY.
 */
class ReportRegistry
{
    /**
     * خريطة المفتاح => class.
     */
    private const REGISTRY = [
        TrialBalanceReport::class,
        GeneralLedgerReport::class,
        AccountStatementReport::class,
        VouchersReport::class,
        ItemLedgerReport::class,
        SalesInvoicesReport::class,
        PurchaseInvoicesReport::class,
    ];

    /**
     * كاش مثيلات (كل تقرير stateless — نسخة واحدة تكفي).
     *
     * @var array<string, Report>
     */
    private static array $instances = [];

    /**
     * كل التقارير المسجلة.
     *
     * @return array<string, Report> مفتاح => مثيل
     */
    public static function all(): array
    {
        $reports = [];

        foreach (self::REGISTRY as $class) {
            $report = self::make($class);
            $reports[$report->key()] = $report;
        }

        return $reports;
    }

    /**
     * جلب تقرير بمفتاحه.
     *
     * @throws InvalidArgumentException إذا كان المفتاح غير مسجل
     */
    public static function get(string $key): Report
    {
        foreach (self::REGISTRY as $class) {
            $report = self::make($class);

            if ($report->key() === $key) {
                return $report;
            }
        }

        throw new InvalidArgumentException("التقرير غير موجود: {$key}");
    }

    /**
     * التحقق من وجود تقرير.
     */
    public static function has(string $key): bool
    {
        try {
            self::get($key);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * إنشاء مثيل التقرير مع تخزين مؤقت.
     */
    private static function make(string $class): Report
    {
        if (!isset(self::$instances[$class])) {
            if (!is_subclass_of($class, Report::class)) {
                throw new InvalidArgumentException(
                    "{$class} لا ينفّذ عقد Report"
                );
            }

            self::$instances[$class] = app($class);
        }

        return self::$instances[$class];
    }
}
