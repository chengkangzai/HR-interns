<?php

namespace App\Filament\Resources\PositionResource\Pages;

use App\Filament\Resources\PositionResource;
use App\Models\Position;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPosition extends ViewRecord
{
    protected static string $resource = PositionResource::class;

    protected function getActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
