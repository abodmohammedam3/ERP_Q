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
     *
     * @var array<string, class-string<Report>>
     */
    private const REGISTRY = [
        'trial-balance'     => TrialBalanceReport::class,
        'general-ledger'    => GeneralLedgerReport::class,
        'account-statement' => AccountStatementReport::class,
        'vouchers'          => VouchersReport::class,
        'item-ledger'       => ItemLedgerReport::class,
        'sales-invoices'    => SalesInvoicesReport::class,
        'purchase-invoices' => PurchaseInvoicesReport::class,
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

        foreach (self::REGISTRY as $key => $class) {
            $reports[$key] = self::make($class);
        }

        return $reports;
    }

    /**
     * جلب تقرير بمفتاحه، أو null إن لم يكن مسجلاً.
     */
    public static function get(string $key): ?Report
    {
        $class = self::REGISTRY[$key] ?? null;

        return $class !== null ? self::make($class) : null;
    }

    /**
     * التحقق من وجود تقرير.
     */
    public static function has(string $key): bool
    {
        return isset(self::REGISTRY[$key]);
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
