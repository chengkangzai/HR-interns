<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PositionType: string implements HasLabel
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
}
