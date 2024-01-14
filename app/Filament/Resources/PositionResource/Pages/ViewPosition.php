<?php

namespace App\Filament\Resources\PositionResource\Pages;

use App\Filament\Resources\PositionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ViewRecord;

class ViewPosition extends ViewRecord
{
    protected static string $resource = PositionResource::class;

    protected function getActions(): array
    {
        return [
            //
        ];
    }
}
