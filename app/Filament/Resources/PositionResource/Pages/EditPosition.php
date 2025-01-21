<?php

namespace App\Filament\Resources\PositionResource\Pages;

use App\Filament\Resources\PositionResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\EditRecord;

class EditPosition extends EditRecord
{
    protected static string $resource = PositionResource::class;

    protected function getActions(): array
    {
        $actions = [];

        foreach ($this->record?->urls ?? [] as $url) {
            $actions[] = Action::make('view_at.'.$url['source'])
                ->label('View At '.$url['source'])
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url($url['url'], true);
        }

        return [
            EditAction::make(),
            DeleteAction::make(),
            ActionGroup::make($actions),
        ];
    }
}
