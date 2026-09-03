<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;

class CharAccount extends Model
{
    protected $table = 'characcount';

    protected $primaryKey = 'accountID';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'accParent',
        'accTypeID',
        'accCode',
        'accName',
        'nature',
        'accLevel',
        'IsActive',
        'isPostable',
    ];
}