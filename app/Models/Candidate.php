<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Candidate extends Model implements HasMedia
{
    use InteractsWithMedia;
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
