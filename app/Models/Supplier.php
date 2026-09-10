<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $table = 'Suppliers';

    protected $primaryKey = 'suplierID';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'accountID',
        'supName',
        'supPhone',
        'supArea',
        'supStoped',
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