<?php

namespace App\Observers;

use App\Models\Accounting\Coin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CoinObserver
{
    /**
     * عند إنشاء عملة جديدة — إذا كانت هي الأساسية، سعرها يجب أن يكون 1
     */
    public function created(Coin $coin): void
    {
        if (!$coin->coinsSystem) return;

        if ((float) $coin->coinsExchangeRate !== 1.0) {
            DB::table('coins')
                ->where('coinsID', $coin->coinsID)
                ->update(['coinsExchangeRate' => 1]);
        }
    }

    /**
     * عند تحديث عملة — إذا أصبحت هي العملة الأساسية الجديدة،
     * نعيد حساب أسعار جميع العملات مقابلها.
     */
    public function updated(Coin $coin): void
    {
        // هل تغيّر حقل coinsSystem؟
        if (!$coin->isDirty('coinsSystem')) {
            return;
        }

        $wasSystem = (bool) $coin->getOriginal('coinsSystem');
        $isSystem  = (bool) $coin->coinsSystem;

        // نتعامل فقط مع الانتقال من 0 → 1 (أصبحت الأساسية للتو)
        if ($wasSystem || !$isSystem) {
            return;
        }

        $this->recalculateRates($coin);
    }

    /**
     * إعادة حساب أسعار جميع العملات مقابل العملة الأساسية الجديدة
     */
    private function recalculateRates(Coin $coin): void
    {
        // سعر العملة الجديدة قبل التحديث (مقابل الأساسية القديمة)
        $oldRate = (float) $coin->getOriginal('coinsExchangeRate');

        // حماية: لو السعر القديم صفر أو سالب — لا يمكن الحساب
        if ($oldRate <= 0) {
            Log::warning('CoinObserver: cannot recalculate — old base rate is 0 or null', [
                'coin_id'   => $coin->coinsID,
                'coin_code' => $coin->coinsCode,
            ]);

            // على الأقل: اضبط العملة الأساسية الجديدة على 1
            DB::table('coins')
                ->where('coinsID', $coin->coinsID)
                ->update(['coinsExchangeRate' => 1]);

            return;
        }

        $factor = 1 / $oldRate;

        DB::transaction(function () use ($coin, $factor, $oldRate) {

            // 1. اضرب أسعار جميع العملات الأخرى في المعامل
            DB::table('coins')
                ->where('coinsID', '!=', $coin->coinsID)
                ->update([
                    'coinsExchangeRate' => DB::raw(
                        'coinsExchangeRate * ' . (float) $factor
                    ),
                ]);

            // 2. العملة الأساسية الجديدة = 1
            DB::table('coins')
                ->where('coinsID', $coin->coinsID)
                ->update(['coinsExchangeRate' => 1]);

            Log::info('CoinObserver: system currency changed, rates recalculated', [
                'new_base_id'   => $coin->coinsID,
                'new_base_code' => $coin->coinsCode,
                'old_rate'      => $oldRate,
                'factor'        => $factor,
            ]);
        });
    }
}