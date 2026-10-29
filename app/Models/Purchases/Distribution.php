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

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isInvoiced(): bool
    {
        return $this->status === self::STATUS_INVOICED;
    }

    public static function nextCode(): string
    {
        $year = now()->format('Y');
        $last = static::whereYear('created_at', $year)
            ->orderByDesc('id')
            ->value('code');

        $seq = 1;
        if ($last && preg_match('/DIST-'.$year.'-(\d+)/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return sprintf('DIST-%s-%04d', $year, $seq);
    }
}