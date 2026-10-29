<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    protected $table = 'reservations';
    protected $primaryKey = 'reservation_id';

    protected $fillable = [
        'reservation_number',
        'customer_id',
        'batch_id',
        'house_id',
        'quantity_birds',
        'weight_kg',
        'target_weight_per_bird',
        'unit_price',
        'total_amount',
        'advance_payment',
        'actual_pickup_date',
        'accepted_quantity',
        'actual_weight_kg',
        'actual_price',
        'receipt_number',
        'rejected_quantity',
        'rejection_reason',
        'pickup_date',
        'status',
        'notes',
        'created_by'
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id', 'batch_id');
    }

    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class, 'house_id', 'house_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function getRemainingBalanceAttribute(): float
    {
        return max(0.0, (float)$this->total_amount - (float)$this->advance_payment);
    }
}
