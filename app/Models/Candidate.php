<?php

namespace App\Models;

use App\Enums\CandidateStatus;
use App\Enums\PositionStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Tags\HasTags;
use Tiptap\Editor;

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
    ];

    protected function workingExperiences(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                $experiences = is_string($value) ? json_decode($value, true) : $value;

                if (! is_array($experiences)) {
                    return collect([]);
                }

                // Convert any string responsibilities to TipTap JSON format
                foreach ($experiences as &$experience) {
                    if (isset($experience['responsibilities']) && is_string($experience['responsibilities']) && ! empty($experience['responsibilities'])) {
                        $editor = new Editor;
                        $experience['responsibilities'] = $editor
                            ->setContent($experience['responsibilities'])
                            ->getDocument();
                    }
                }

                return collect($experiences);
            },
            set: fn ($value) => json_encode($value),
        );
    }

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
