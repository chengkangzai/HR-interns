<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Candidate extends Model
{
    use SoftDeletes, HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone_number',
        'from',
        'to',
    ];

    protected $casts = [
        'from' => 'datetime',
        'to' => 'datetime',
    ];
}
