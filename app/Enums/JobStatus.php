<?php

namespace App\Enums;

use Filament\Models\Contracts\HasName;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum JobStatus: string implements HasLabel,HasColor
{
    case OPEN = 'open';
    case CLOSED = 'closed';

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::OPEN => 'success',
            self::CLOSED => 'danger',
        };
    }

    public function getLabel(): ?string
    {
        return match ($this) {
            self::OPEN => 'Open',
            self::CLOSED => 'Closed',
        };
    }
}
