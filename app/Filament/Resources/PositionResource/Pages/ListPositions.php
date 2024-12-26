<?php

namespace App\Filament\Resources\PositionResource\Pages;

use App\Enums\PositionType;
use App\Filament\Resources\PositionResource;
use App\Models\Position;
use Filament\Actions\CreateAction;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPositions extends ListRecords
{
    protected static string $resource = PositionResource::class;

    protected function getActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $positions = PositionType::cases();
        $tabs = [
            'all' => Tab::make(),
        ];
        foreach ($positions as $position) {
            $count = Position::where('type', $position->value)->count();
            if ($count >= 1) {
                $tabs[$position->value] = Tab::make()
                    ->badgeColor($position->getColor())
                    ->badge($count)
                    ->modifyQueryUsing(fn (Builder $query) => $query->where('type', $position->value));
            }
        }

        return $tabs;
    }
}
