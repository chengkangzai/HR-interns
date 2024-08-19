<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Rawilk\FilamentPasswordInput\Password;
use STS\FilamentImpersonate\Tables\Actions\Impersonate;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationGroup = 'User Management';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->unique(ignoreRecord: true)
                    ->required(),
                Password::make('password')
                    ->required()
                    ->minLength(8)
                    ->placeholder('Password must contain at least 8 characters')
                    ->visibleOn('create'),
                Forms\Components\Select::make('role_id')
                    ->relationship('roles', 'name', fn (Builder $query) => $query->whereKeyNot(1))
                    ->label('Role')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Role')
                    ->searchable()
                    ->badge(),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                if (Auth::user()->hasRole('HR')) {
                    $query->whereKeyNot(1);
                }
            })
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->relationship('roles', 'name', function (Builder $query) {
                        if (Auth::user()->hasRole('HR')) {
                            $query->whereKeyNot(1);
                        }
                    }),
            ])
            ->defaultGroup('roles.name')
            ->defaultSort('name')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (User $record) => $record->id == Auth::id()),
                Impersonate::make('impersonate'),
            ])
            ->actionsAlignment('left');
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
            'index' => Pages\ListUsers::route('/'),
        ];
    }
}
