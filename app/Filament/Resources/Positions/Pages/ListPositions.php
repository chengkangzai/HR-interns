<?php

namespace App\Filament\Resources\Positions\Pages;

use App\Enums\PositionStatus;
use App\Enums\PositionType;
use App\Filament\Resources\Positions\PositionResource;
use App\Models\Position;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
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
            $count = Position::where('type', $position->value)
                ->where('status', PositionStatus::OPEN)
                ->count();
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
