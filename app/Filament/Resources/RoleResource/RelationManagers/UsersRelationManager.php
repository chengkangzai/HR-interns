<?php

namespace App\Filament\Resources\RoleResource\RelationManagers;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use STS\FilamentImpersonate\Tables\Actions\Impersonate;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->required('create')
                    ->maxLength(255),
                TextInput::make('email')
                    ->email()
                    ->unique(ignoreRecord: true)
                    ->required(),
                Select::make('roles')
                    ->label('Role')
                    ->relationship('roles', 'name')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge(),
            ])
            ->filters([
                //
            ])
            ->defaultSort('name')
            ->actions([
                Impersonate::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
