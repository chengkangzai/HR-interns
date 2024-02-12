<?php

namespace App\Filament\Resources\CandidateResource\Pages;

use App\Filament\Resources\CandidateResource;
use App\Models\Candidate;
use Closure;
use Filament\Resources\Pages\Page;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Livewire\Component;
use ReflectionClass;
use Spatie\Activitylog\Models\Activity;
use Z3d0X\FilamentLogger\Resources\ActivityResource;

class AuditCandidate extends Page implements HasTable
{
    use InteractsWithTable;

    public ?Candidate $record;

    protected static string $resource = CandidateResource::class;

    protected static string $view = 'filament.resources.candidate-resource.pages.audit-candidate';

    protected function getTableActions(): array
    {
        return [
            Action::make('View')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->url(fn (Activity $record) => ActivityResource::getUrl('view', ['record' => $record])),
        ];
    }

    protected function getTableRecordUrlUsing(): ?Closure
    {
        return fn (Activity $record) => ActivityResource::getUrl('view', ['record' => $record]);
    }

    public static function getTableColumns(): array
    {
        return [
            TextColumn::make('id')
                ->label('ID')
                ->sortable(),
            TextColumn::make('event')
                ->toggleable()
                ->label('Event'),
            TextColumn::make('description')
                ->toggleable()
                ->toggledHiddenByDefault()
                ->label('Description'),
            TextColumn::make('causer')
                ->label('User')
                ->formatStateUsing(fn (TextColumn $column) => $column->getRecord()->causer?->name ?? 'System'),
            TextColumn::make('subject_type')
                ->hidden(fn (Component $livewire) => $livewire instanceof RelationManager)
                ->label('Subject')
                ->formatStateUsing(function (TextColumn $column) {
                    /** @var Activity $record */
                    $record = $column->getRecord();

                    return ($subject_type = $record->subject_type)
                        ? (new ReflectionClass($subject_type))->getShortName()
                        : 'System';
                }),
            TextColumn::make('created_at')
                ->label('Date Time')
                ->dateTime()
                ->sortable(),
        ];
    }

    protected function getTableQuery(): Builder|Relation
    {
        return Activity::query()
            ->with('causer')
            ->whereHasMorph('subject', Candidate::class, fn (Builder $query) => $query
                ->withTrashed()
                ->where('id', '=', $this->record->id)
            )
            ->orderByDesc('created_at');
    }
}
