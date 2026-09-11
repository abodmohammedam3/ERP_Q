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


    /*
    |--------------------------------------------------------------------------
    | الحقول القابلة للتعبئة
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'accParent',
        'accTypeID',
        'accCode',
        'accName',
        'nature',
        'accLevel',
        'IsActive',
        'isPostable',
        'is_system',
        'system_key',
    ];


    /*
    |--------------------------------------------------------------------------
    | أنواع البيانات
    |--------------------------------------------------------------------------
    */

    protected $casts = [
        'accountID'  => 'integer',
        'accParent'  => 'integer',
        'accTypeID'  => 'integer',
        'accLevel'   => 'integer',
        'nature'     => 'integer',
        'isPostable' => 'integer',
        'IsActive'   => 'integer',
        'is_system'  => 'integer',
    ];
}

