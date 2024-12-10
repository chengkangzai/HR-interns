<?php

namespace App\Enums;

enum PositionType: string
{
    case INTERN = 'intern';
    case CONTRACT = 'contract';
    case FULL_TIME = 'full_time';
    case PART_TIME = 'part_time';
}
