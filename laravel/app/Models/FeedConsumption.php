<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedConsumption extends Model
{
    protected $table = 'feed_consumption';
    protected $primaryKey = 'consumption_id';
    const UPDATED_AT = null;

    protected $fillable = [
        'batch_id',
        'inventory_id',
        'feed_stage',
        'quantity_used',
        'date',
        'recorded_by',
        'remarks',
        'unit_cost_snapshot'
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id', 'batch_id');
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class, 'inventory_id', 'inventory_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by', 'user_id');
    }
}
