<?php

namespace App\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CandidateStatus: string implements HasLabel,HasColor
{
    case PENDING = 'pending';
    case TECHNICAL_TEST = 'technical_test';
    case INTERVIEW = 'interview';
    case EXPIRED = 'expired';
    case WITHDRAWN = 'withdrawn';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::TECHNICAL_TEST => 'Technical Test',
            self::INTERVIEW => 'Interview',
            self::EXPIRED => 'Expired',
            self::WITHDRAWN => 'Withdrawn',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PENDING => Color::Gray,
            self::TECHNICAL_TEST => Color::Blue,
            self::INTERVIEW => Color::Green,
            self::EXPIRED => Color::Red,
            self::WITHDRAWN => Color::Orange,
        };
    }
}
