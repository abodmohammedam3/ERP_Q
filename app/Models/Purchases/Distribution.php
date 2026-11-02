<?php

namespace App\Models\Purchases;

use Illuminate\Database\Eloquent\Model;

class Distribution extends Model
{
    protected $table = 'distributions';
    protected $guarded = ['id'];

    protected $casts = [
        'distribution_date' => 'date',
        'invoiced_at'       => 'datetime',
    ];

    public const STATUS_DRAFT    = 'draft';
    public const STATUS_INVOICED = 'invoiced';

    public function details()
    {
        return $this->hasMany(DistributionDetail::class, 'distribution_id');
    }

    /** سجل تتبع التوزيع (Audit Log) */
    public function logs()
    {
        return $this->hasMany(DistributionLog::class, 'distribution_id');
    }

    /**
     * تسجيل فعالية على التوزيع (Audit Log).
     * @param string $action       created | updated | issued | deleted
     * @param string|null $description وصف عربي
     * @param array  $meta         بيانات إضافية (رقم الفاتورة، لقطة الصفوف...)
     */
    public function log(string $action, ?string $description = null, array $meta = []): DistributionLog
    {
        return DistributionLog::create([
            'distribution_id' => $this->id,
            'action'          => $action,
            'description'     => $description,
            'user_id'         => auth()->id(),
            'meta'            => $meta ?: null,
        ]);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isInvoiced(): bool
    {
        return $this->status === self::STATUS_INVOICED;
    }

    /**
     * ✅ توليد رقم توزيع فريد مع قفل الصف لمنع التكرار.
     * يجب أن يُستدعى داخل DB::transaction.
     */
    public static function nextCode(): string
    {
        $year   = now()->format('Y');
        $prefix = "DIST-{$year}-";

        $last = static::whereYear('created_at', $year)
            ->where('code', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('code');

        $seq = 1;
        if ($last && preg_match('/DIST-'.$year.'-(\d+)/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return sprintf('DIST-%s-%04d', $year, $seq);
    }
}