<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $table = 'customers';
    protected $primaryKey = 'customer_id';

    protected $fillable = [
        'customer_code',
        'name',
        'contact_person',
        'phone',
        'email',
        'address',
        'customer_type',
        'is_active'
    ];

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'customer_id', 'customer_id');
    }
}
