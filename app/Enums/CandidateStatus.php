<?php

namespace App\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CandidateStatus: string implements HasColor, HasLabel
{
    case PENDING = 'pending'; // Indicates the candidate has been sent an invitation
    case TECHNICAL_TEST = 'technical_test'; // Indicates the candidate has been sent a technical test
    case INTERVIEW = 'interview'; // Indicates the candidate has been interviewed
    case WITHDRAWN = 'withdrawn'; // Indicates the candidate has withdrawn from the process
    case HIRED = 'hired'; // Indicates the candidate has accepted the offer and has started
    case OFFER_ACCEPTED = 'offer_accepted'; // Indicates the candidate has accepted the offer but has not yet started
    case COMPLETED = 'completed'; // Indicates the candidate has completed internship

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::TECHNICAL_TEST => 'Technical Test',
            self::INTERVIEW => 'Interview',
            self::WITHDRAWN => 'Withdrawn',
            self::HIRED => 'Hired',
            self::OFFER_ACCEPTED => 'Offer Accepted',
            self::COMPLETED => 'Completed',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PENDING => Color::Gray,
            self::TECHNICAL_TEST => Color::Blue,
            self::INTERVIEW => Color::Yellow,
            self::WITHDRAWN => Color::Orange,
            self::HIRED => Color::Lime,
            self::OFFER_ACCEPTED => Color::Sky,
            self::COMPLETED => Color::Green,
        };
    }
}
