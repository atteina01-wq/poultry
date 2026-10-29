<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class House extends Model
{
    protected $table = 'houses';
    protected $primaryKey = 'house_id';

    protected $fillable = [
        'house_name',
        'capacity',
        'status',
        'current_batch_id',
        'vacant_date',
        'notes'
    ];

    public function currentBatch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'current_batch_id', 'batch_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'house_id', 'house_id');
    }
}
