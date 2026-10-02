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
        'is_system',
        'system_key',
    ];

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

    // الحساب الأب
    public function parent()
    {
        return $this->belongsTo(
            self::class,
            'accParent',
            'accountID'
        );
    }

    // الحسابات الأبناء
    public function children()
    {
        return $this->hasMany(
            self::class,
            'accParent',
            'accountID'
        );
    }
}