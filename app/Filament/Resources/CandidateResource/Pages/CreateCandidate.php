<?php

namespace App\Filament\Resources\CandidateResource\Pages;

use App\Filament\Resources\CandidateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCandidate extends CreateRecord
{
    protected static string $resource = CandidateResource::class;

    protected function getActions(): array
    {
        return [

        ];
    }
}
