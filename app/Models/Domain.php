<?php

// app/Models/Domain.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Domain extends Model
{
    protected $fillable = [
        'domain',
        'exp_date',
        'registrar',
    ];
}
