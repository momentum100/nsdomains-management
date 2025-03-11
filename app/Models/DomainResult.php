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
        'user_id',
        'registrar',
        'expiration_date',
        'days_left',
        'price',
            
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
