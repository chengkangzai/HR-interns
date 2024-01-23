<?php

namespace App\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum CandidateStatus: string implements HasColor, HasDescription, HasLabel
{
    case PENDING = 'pending'; // Indicates the candidate has been recorded but not contacted
    case CONTACTED = 'contacted'; // Indicates the candidate has been contacted
    case TECHNICAL_TEST = 'technical_test'; // Indicates the candidate has been sent a technical test
    case INTERVIEW = 'interview'; // Indicates the candidate has been interviewed
    case WITHDRAWN = 'withdrawn'; // Indicates the candidate has withdrawn from the process
    case HIRED = 'hired'; // Indicates the candidate has accepted the offer and has started
    case OFFER_ACCEPTED = 'offer_accepted'; // Indicates the candidate has accepted the offer but has not yet started
    case COMPLETED = 'completed'; // Indicates the candidate has completed internship
    case NO_RESPONSE = 'no_response'; // Indicates the candidate has not responded to the invitation

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::CONTACTED => 'Contacted',
            self::TECHNICAL_TEST => 'Technical Test',
            self::INTERVIEW => 'Interview',
            self::WITHDRAWN => 'Withdrawn',
            self::HIRED => 'Hired',
            self::OFFER_ACCEPTED => 'Offer Accepted',
            self::COMPLETED => 'Completed',
            self::NO_RESPONSE => 'No Response',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PENDING => Color::Gray,
            self::CONTACTED => Color::Indigo,
            self::TECHNICAL_TEST => Color::Blue,
            self::INTERVIEW => Color::Yellow,
            self::WITHDRAWN => Color::Orange,
            self::HIRED => Color::Lime,
            self::OFFER_ACCEPTED => Color::Sky,
            self::COMPLETED => Color::Green,
            self::NO_RESPONSE => Color::Red,
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::PENDING => 'Candidate has been recorded but not contacted',
            self::CONTACTED => 'Candidate has been contacted',
            self::TECHNICAL_TEST => 'Candidate has been sent a technical test',
            self::INTERVIEW => 'Candidate has been interviewed',
            self::WITHDRAWN => 'Candidate has withdrawn from the process',
            self::HIRED => 'Candidate has accepted the offer and has started',
            self::OFFER_ACCEPTED => 'Candidate has accepted the offer but has not yet started',
            self::COMPLETED => 'Candidate has completed internship',
            self::NO_RESPONSE => 'Candidate has not responded to the invitation',
        };
    }
}
