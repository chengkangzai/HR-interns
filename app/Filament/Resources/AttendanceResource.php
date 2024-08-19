<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttendanceResource\Pages;
use App\Models\Attendance;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class AttendanceResource extends Resource
{
    protected static ?string $model = Attendance::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationGroup = 'Attendance';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('date')
                    ->default(now()->toFormattedDateString())
                    ->disabled(),
                TextInput::make('time_in')
                    ->label('Time In')
                    ->default(now()->toTimeString())
                    ->disabled(),
                Forms\Components\Textarea::make('remarks')
                    ->visible(fn (Get $get) => $get('time_in') > '09:15:00')
                    ->required()
                    ->minLength(10)
                    ->maxLength(50)
                    ->placeholder('Please provide the reason of being late. Keep it simple.')
                    ->rows(4)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')
                    ->date()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Employee')
                    ->searchable(! Auth::user()->hasRole('Employee'))
                    ->hidden(Auth::user()->hasRole('Employee')),
                TextColumn::make('time_in')
                    ->label('Time In')
                    ->sortable(),
                TextColumn::make('time_out')
                    ->label('Time Out')
                    ->sortable(),
                TextColumn::make('remarks')
                    ->toggleable(),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                if (Auth::user()->hasRole('Employee')) {
                    $query->where('user_id', Auth::id());
                }
            })
            ->filters([
                Filter::make('attendance')
                    ->form([
                        DatePicker::make('from'),
                        DatePicker::make('to'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '>=', $date),
                            )
                            ->when(
                                $data['to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '<=', $date),
                            );
                    }),
                SelectFilter::make('employee')
                    ->relationship('user', 'name', fn (Builder $query) => $query->whereRelation('roles', 'name', 'Employee'))
                    ->searchable()
                    ->preload()
                    ->hidden(Auth::user()->hasRole('Employee')),
            ])
            ->defaultSort('date', 'desc')
            ->actions([
                Action::make('clock_out')
                    ->icon('heroicon-o-clock')
                    ->label('Clock Out')
                    ->color(Color::Green)
                    ->form([
                        TextInput::make('date')
                            ->default(now()->toFormattedDateString())
                            ->disabled(),
                        TextInput::make('time_out')
                            ->label('Time Out')
                            ->default(now()->toTimeString())
                            ->disabled(),
                    ])
                    ->modalSubmitActionLabel('Clock Out')
                    ->action(function (Attendance $record) {
                        $record->update([
                            'time_out' => now()->toTimeString(),
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Clocked out successfully')
                            ->send();
                    })
                    ->visible(fn (Attendance $record) => Auth::user()->hasRole('Employee') && $record->time_out == null),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttendances::route('/'),
            'create' => Pages\CreateAttendance::route('/clock-in'),
        ];
    }
}
