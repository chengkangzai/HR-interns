<?php

namespace App\Models;

use App\Enums\CandidateStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Candidate extends Model implements HasMedia
{
    use InteractsWithMedia;
    use Notifiable;

    use SoftDeletes, HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone_number',
        'from',
        'to',
        'notes',
        'status',
        'job_id'
    ];

    protected $casts = [
        'from' => 'datetime',
        'to' => 'datetime',
        'status' => CandidateStatus::class
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }
}
