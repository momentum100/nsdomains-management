<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DomainResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'domain',
        'registrar',
        'expiration_date',
        'days_left',
        'price',
    ];
}
