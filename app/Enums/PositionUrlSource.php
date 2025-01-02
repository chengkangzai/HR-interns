<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PositionUrlSource: string implements HasLabel
{
    case INDEED = 'indeed';
    case LINKED_IN = 'linked_in';
    case DEV_KAKI = 'dev_kakai';
    case SUNWAY_PORTAL = 'sunway_portal';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::INDEED => 'Indeed',
            self::LINKED_IN => 'Linked In',
            self::DEV_KAKI => 'Dev Kaki',
            self::SUNWAY_PORTAL => 'Sunway Portal',
        };
    }
}
