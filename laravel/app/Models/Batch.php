<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Batch extends Model
{
    protected $table = 'batches';
    protected $primaryKey = 'batch_id';

    protected $fillable = [
        'batch_code',
        'arrival_date',
        'production_date',
        'initial_quantity',
        'current_quantity',
        'supplier',
        'delivery_receipt_no',
        'chick_unit_cost',
        'target_weight_kg',
        'current_weight_kg',
        'status',
        'flock_type',
        'breed',
        'house_pen',
        'expected_harvest_date',
        'notes',
        'created_by'
    ];

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'batch_id', 'batch_id');
    }

    public function mortalityRecords(): HasMany
    {
        return $this->hasMany(MortalityRecord::class, 'batch_id', 'batch_id');
    }

    public function healthRecords(): HasMany
    {
        return $this->hasMany(HealthRecord::class, 'batch_id', 'batch_id');
    }

    public function feedConsumptions(): HasMany
    {
        return $this->hasMany(FeedConsumption::class, 'batch_id', 'batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function getAgeDaysAttribute(): int
    {
        if (empty($this->arrival_date)) return 0;
        $arrival = new \DateTime($this->arrival_date);
        $today = new \DateTime();
        $diff = $today->diff($arrival);
        return max(1, (int)$diff->days);
    }
}
