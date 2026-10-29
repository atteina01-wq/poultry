<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthRecord extends Model
{
    protected $table = 'health_records';
    protected $primaryKey = 'health_id';
    const UPDATED_AT = null;

    protected $fillable = [
        'batch_id',
        'number_of_chickens_treated',
        'inventory_id',
        'type',
        'medicine_name',
        'illness_observation',
        'doctor_consultation',
        'diagnosis',
        'quantity_used',
        'date',
        'notes',
        'status',
        'recorded_by',
        'next_due_date'
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
