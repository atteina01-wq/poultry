<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyProfile extends Model
{
    protected $table = 'company_profile';
    public $timestamps = false;

    protected $fillable = [
        'company_name',
        'address',
        'contact_number',
        'email',
        'additional_info',
        'logo'
    ];
}
