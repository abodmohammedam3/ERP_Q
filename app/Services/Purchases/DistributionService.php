<?php

namespace App\Services\Purchases;

use App\Models\Accounting\Coin;
use App\Models\Customer;
use App\Models\Purchases\Distribution;
use App\Models\Purchases\DistributionDetail;
use App\Models\Purchases\DistributionLog;
use App\Models\Sales\SalesInvoice;
use App\Services\Sales\SalesInvoiceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * خدمة توزيع أصناف المشتريات
 * ---------------------------------------------------------
 * - مسؤولة عن إصدار فاتورة بيع حقيقية (SalesInvoice) لكل عميل
 *   من صفوف التوزيع المحددة، عبر SalesInvoiceService الموجود
 *   — أي أن الإصدار يمر بالمسار نفسه الذي تمر به فواتير البيع
 *   اليدوية: فحص توفر المخزون + حركة مخزون (خصم) + قيد محاسبي.
 * - تعلّم صفوف التوزيع بـ sales_invoice_id + invoiced_at للتفاف.
 */
class DistributionService
{
    public function __construct(protected SalesInvoiceService $salesInvoiceService)
    {
    }

    // =========================================================
    // إصدار فاتورة بيع حقيقية لعميل من توزيعات محددة
    // =========================================================

    /**
     * @param  int   $customerId        عميل جدول customers
     * @param  array $distributionIds   معرفات توزيعات (distributions.id)
     * @return SalesInvoice  فاتورة البيع المنشأة (مرحّلة تلقائيًا مع حركة مخزون + قيد)
     */
    public function issueInvoice(int $customerId, array $distributionIds): SalesInvoice
    {
        return DB::transaction(function () use ($customerId, $distributionIds) {

            $details = $this->lockDetailsForInvoicing($customerId, $distributionIds);

            if ($details->isEmpty()) {
                throw ValidationException::withMessages([
                    'distribution_ids' => 'لا توجد تفاصيل قابلة للفوترة.',
                ]);
            }

            // 1) حماية: التوزيع الذي يحتوي صفًّا غير مفوتر يجب أن يكون مسودة
            $this->guardDistributionsAreDraft($details);

            // 2) تجهيز العميل والحساب المحاسبي
            $customer = Customer::query()->findOrFail($customerId);

            if (empty($customer->accountID)) {
                throw ValidationException::withMessages([
                    'customer_id' => 'العميل «' . $customer->CustomersName2
                        . '» غير مرتبط بحساب محاسبي في دليل الحسابات.',
                ]);
            }

            // 3) العملة الأساسية للنظام (سعرها 1)
            $systemCoin = Coin::query()
                ->where('coinsSystem', 1)
                ->where('is_active', 1)
                ->first();

            if (!$systemCoin) {
                throw ValidationException::withMessages([
                    'coin_id' => 'لا توجد عملة نظام أساسية (coinsSystem = 1) معرّفة.',
                ]);
            }

            // 4) تجميع أكواد التوزيعات المشاركة للبيان
            $distCodes = $details->map(fn ($d) => $d->distribution?->code)
                ->filter()->unique()->values()->implode('، ');

            // 5) تجهيز تفاصيل فاتورة البيع من صفوف التوزيع
            $invoiceDetails = $details->map(function (DistributionDetail $d) {
                $md = $d->movementDetail;

                if (!$md) {
                    throw ValidationException::withMessages([
                        'details' => "صف التوزيع رقم {$d->id} غير مرتبط بحركة مخزون صحيحة.",
                    ]);
                }

                return [
                    'item_id'             => (int) $md->item_id,
                    'type_id'             => $md->type_id !== null ? (int) $md->type_id : null,
                    'unit_id'             => $md->unit_id !== null ? (int) $md->unit_id : null,
                    'warehouse_id'        => (int) $md->warehouse_id,
                    'supplier_account_id' => $md->supplier_account_id !== null
                        ? (int) $md->supplier_account_id
                        : null,
                    'code'                => $md->code,
                    'quantity'            => (float) $d->quantity,
                    'price'               => (float) $d->sale_price,
                    'cost_price'          => (float) $d->unit_cost,
                    'discount'            => 0,
                ];
            })->all();

            // 6) إنشاء فاتورة البيع عبر الخدمة الموجودة
            $salesInvoice = $this->salesInvoiceService->create([
                'invoice_date'   => now()->toDateString(),
                'account_id'     => (int) $customer->accountID,
                'payment_method' => SalesInvoice::PAYMENT_CREDIT,
                'coin_id'        => (int) $systemCoin->coinsID,
                'exchange_rate'  => 1,
                'statement'      => 'توزيع أصناف مشتريات'
                    . ($distCodes !== '' ? ' — ' . $distCodes : ''),
                'reference'      => $distCodes !== '' ? $distCodes : null,
                'details'        => $invoiceDetails,
                'skip_stock_validation' => true, // ✅ إضافة هذا السطر لتخطي فحص المخزون
            ]);

            // 7) ربط صفوف التوزيع بالفاتورة الناتجة
            DistributionDetail::query()
                ->whereIn('id', $details->pluck('id'))
                ->update([
                    'sales_invoice_id' => $salesInvoice->sales_invoice_id,
                    'invoiced_at'      => now(),
                ]);

            /* المتبقي غير المفوتر لكل توزيع */
            $remainingByDist = DistributionDetail::query()
                ->whereIn('distribution_id', $distributionIds)
                ->whereNull('invoiced_at')
                ->groupBy('distribution_id')
                ->selectRaw('distribution_id, COUNT(*) as c')
                ->pluck('c', 'distribution_id');

            // ✅ Audit Log: لكل توزيع مشارك
            $details->pluck('distribution_id')->unique()->each(function ($distId) use ($details, $salesInvoice, $customer, $customerId, $remainingByDist) {
                $dist = Distribution::find($distId);
                if (! $dist) return;

                $distRows = $details->where('distribution_id', $distId);
                $partial  = ($remainingByDist[$distId] ?? 0) > 0;

                $dist->log(
                    $partial
                        ? DistributionLog::ACTION_PARTIALLY_ISSUED
                        : DistributionLog::ACTION_ISSUED,
                    "إصدار " . ($partial ? 'جزئي من ' : '')
                        . "فاتورة البيع {$salesInvoice->invoice_number} للعميل "
                        . ($customer->CustomersName2 ?? $customerId)
                        . " — " . $distRows->count() . " صف",
                    [
                        'sales_invoice_id'     => $salesInvoice->sales_invoice_id,
                        'sales_invoice_number' => $salesInvoice->invoice_number,
                        'total'                => (float) $salesInvoice->total_in_invoice_currency,
                        'customer_id'          => $customerId,
                        'details_count'        => $distRows->count(),
                        'partial'              => $partial,
                    ],
                );
            });

            // 8) إغلاق التوزيعات المكتملة
            $fullyInvoiced = collect($distributionIds)
                ->reject(fn ($id) => isset($remainingByDist[$id]))
                ->values();

            if ($fullyInvoiced->isNotEmpty()) {
                Distribution::query()
                    ->whereIn('id', $fullyInvoiced)
                    ->where('status', Distribution::STATUS_DRAFT)
                    ->update([
                        'status'      => Distribution::STATUS_INVOICED,
                        'invoiced_at' => now(),
                        'invoiced_by' => auth()->id(),
                    ]);
            }

            return $salesInvoice;
        });
    }

    // =========================================================
    // خصم صفوف التوزيع + قفلها من التزامن
    // =========================================================
    protected function lockDetailsForInvoicing(int $customerId, array $distributionIds)
    {
        return DistributionDetail::query()
            ->whereIn('distribution_id', $distributionIds)
            ->where('customer_id', $customerId)
            ->whereNull('invoiced_at')
            ->with([
                'distribution:id,code,status',
                'movementDetail.item:itemID,itemName2',
            ])
            ->lockForUpdate()
            ->get();
    }

    // =========================================================
    // حماية من إصدار صفوف ضمن توزيع مفوتر جزئيًّا
    // =========================================================
    protected function guardDistributionsAreDraft($details): void
    {
        $distIds = $details->pluck('distribution_id')->unique();

        $bad = Distribution::query()
            ->whereIn('id', $distIds)
            ->where('status', '!=', Distribution::STATUS_DRAFT)
            ->get();

        if ($bad->isNotEmpty()) {
            throw ValidationException::withMessages([
                'distribution_ids' => 'التوزيعات التالية مفوترة مسبقًا ولا يمكن إصدارها: '
                    . $bad->pluck('code')->implode('، '),
            ]);
        }
    }
}