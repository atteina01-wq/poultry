<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MortalityRecord extends Model
{
    protected $table = 'mortality_records';
    protected $primaryKey = 'mortality_id';
    const UPDATED_AT = null;

    protected $fillable = [
        'batch_id',
        'date',
        'quantity_change',
        'reason',
        'financial_value',
        'recorded_by'
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id', 'batch_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by', 'user_id');
    }

    public function getBirdsLostAttribute(): int
    {
        return abs((int)$this->quantity_change);
    }
}
