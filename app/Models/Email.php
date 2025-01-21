<?php

namespace App\Models;

use App\Enums\PositionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Email extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'title',
        'body',
        'cc',
        'sort',
        'position_id',
    ];

    protected $casts = [
        'cc' => 'array',
    ];

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function openPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class)
            ->where('status', PositionStatus::OPEN);
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
