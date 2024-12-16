<?php

namespace App\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PositionType: string implements HasColor, HasLabel
{
    case INTERN = 'intern';
    case CONTRACT = 'contract';
    case FULL_TIME = 'full_time';
    case PART_TIME = 'part_time';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::INTERN => 'Internship',
            self::CONTRACT => 'Contract Position',
            self::FULL_TIME => 'Full-Time Position',
            self::PART_TIME => 'Part-Time Position',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::INTERN => Color::Cyan,
            self::CONTRACT => Color::Lime,
            self::FULL_TIME => Color::Violet,
            self::PART_TIME => Color::Fuchsia,
        };
    }
}
