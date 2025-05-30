<?php

namespace App\Models;

use App\Enums\CandidateStatus;
use App\Enums\PositionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Tags\HasTags;

class Candidate extends Model implements HasMedia
{
    use HasFactory;
    use HasTags;
    use InteractsWithMedia;
    use LogsActivity;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone_number',
        'from',
        'to',
        'notes',
        'status',
        'additional_info',
        'working_experiences',
        'position_id',
    ];

    protected $casts = [
        'from' => 'datetime',
        'to' => 'datetime',
        'status' => CandidateStatus::class,
        'additional_info' => 'collection',
        'working_experiences' => 'collection',
    ];

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function openPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class)->where('status', PositionStatus::OPEN);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('resumes');
        $this->addMediaCollection('other_documents');

        // generated
        $this->addMediaCollection('offer_letters');
        $this->addMediaCollection('wfh_letter');
        $this->addMediaCollection('completion_letter');
        $this->addMediaCollection('attendance_report');
        $this->addMediaCollection('completion_cert');
    }
}
