<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PositionStatus: string implements HasColor, HasLabel
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
