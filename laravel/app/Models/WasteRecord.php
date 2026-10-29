<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WasteRecord extends Model
{
    protected $table = 'waste_records';
    protected $primaryKey = 'waste_id';
    public $timestamps = false;
    const UPDATED_AT = null;

    protected $fillable = [
        'waste_date',
        'waste_type',
        'grade_id',
        'inventory_id',
        'batch_id',
        'quantity',
        'unit',
        'reason',
        'estimated_cost',
        'expense_id',
        'recorded_by',
        'remarks',
        'created_at'
    ];

    public function inventory()
    {
        return $this->belongsTo(Inventory::class, 'inventory_id', 'inventory_id');
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class, 'batch_id', 'batch_id');
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by', 'user_id');
    }
}
