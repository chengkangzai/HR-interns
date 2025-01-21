<?php

namespace App\Models;

use App\Enums\PositionStatus;
use App\Enums\PositionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Position extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'status',
        'type',
        'urls',
    ];

    protected $casts = [
        'status' => PositionStatus::class,
        'type' => PositionType::class,
        'urls' => 'array',
    ];

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }

    public function emails(): HasMany
    {
        return $this->hasMany(Email::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('documents');
    }
}
