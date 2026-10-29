<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activity extends Model
{
    protected $table = 'activities';
    protected $primaryKey = 'activity_id';
    const UPDATED_AT = null;

    protected $fillable = [
        'batch_id',
        'user_id',
        'type',
        'item_name',
        'quantity',
        'date',
        'remarks',
        'status'
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id', 'batch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
