<?php

namespace App\Models\Inventory;

use App\Models\Accounting\CharAccount;
use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    protected $table = 'stocks';
    protected $primaryKey = 'StockID';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'StockName',
        'accountID',
        'is_active',
    ];

    // العلاقة مع دليل الحسابات
    public function account()
    {
        return $this->belongsTo(CharAccount::class, 'accountID', 'accountID');
    }
}