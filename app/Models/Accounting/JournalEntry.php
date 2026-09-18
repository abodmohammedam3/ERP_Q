<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    protected $table      = 'Journal_Entries';
    protected $primaryKey = 'entryID';
    public    $timestamps = false;

    protected $fillable = [
        'entryNo',
        'entryDate',
        'docType',
        'docNumber',
        'description2',
        'totalAmount',
        'createdAt',
    ];

    protected $casts = [
        'entryDate'   => 'date',
        'totalAmount' => 'decimal:2',
        'createdAt'   => 'datetime',
    ];

    // ══════════════════════════════════════════════════════════
    //  العلاقات
    // ══════════════════════════════════════════════════════════

    public function lines(): HasMany
    {
        return $this->hasMany(
            JournalEntryLine::class,
            'entryID',
            'entryID'
        );
    }
}