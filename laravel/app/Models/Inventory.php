<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inventory extends Model
{
    protected $table = 'inventory';
    protected $primaryKey = 'inventory_id';

    protected $fillable = [
        'item_name',
        'category',
        'quantity',
        'unit',
        'unit_cost',
        'reorder_level',
        'description',
        'is_active'
    ];

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'inventory_id', 'inventory_id');
    }

    public function feedConsumptions(): HasMany
    {
        return $this->hasMany(FeedConsumption::class, 'inventory_id', 'inventory_id');
    }

    public function getStockStatusAttribute(): string
    {
        if ($this->quantity <= 0) return 'Out of Stock';
        if ($this->reorder_level && $this->quantity <= $this->reorder_level) return 'Low Stock';
        return 'In Stock';
    }
}
