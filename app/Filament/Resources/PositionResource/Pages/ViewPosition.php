<?php

namespace App\Filament\Resources\PositionResource\Pages;

use App\Filament\Resources\PositionResource;
use App\Models\Position;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/**
 * @property Position $record
 */
class ViewPosition extends ViewRecord
{
    protected static string $resource = PositionResource::class;

    protected function getActions(): array
    {
        $actions = [];

        foreach ($this->record->urls as $url) {
            $actions[] = Action::make('view_at.'.$url['source'])
                ->label('View At '.$url['source'])
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url($url['url'], true);
        }

        return [
            EditAction::make(),
            ActionGroup::make($actions),
        ];
    }
}
