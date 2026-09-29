<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $table = 'customers';

    protected $primaryKey = 'CustomersID';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'accountID',
        'CustomersName2',
        'CusPhone',
        'CusAddress',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function account()
    {
        return $this->belongsTo(
            Accounting\CharAccount::class,
            'accountID',
            'accountID'
        );
    }
}