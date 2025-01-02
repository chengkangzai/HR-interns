<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (User $record) => $record->id !== auth()->user()->id),
            Action::make('reset_password')
                ->visible(fn (User $record) => $record->id !== auth()->user()->id)
                ->icon('heroicon-s-lock-open')
                ->requiresConfirmation()
                ->modalHeading('Reset Password')
                ->modalDescription('Please understand the consequences of this action')
                ->form([
                    TextInput::make('password')
                        ->label('New Password')
                        ->confirmed()
                        ->password()
                        ->revealable(),
                    TextInput::make('password_confirmation')
                        ->label('Confirm New Password')
                        ->password()
                        ->revealable(),
                ])
                ->action(function (User $record, array $data) {
                    $record->update([
                        'password' => bcrypt($data['password']),
                    ]);

                    Notification::make('Password reset successful.')
                        ->success()
                        ->title('Password has reset')
                        ->body('Password reset successfully.')
                        ->send();
                })
        ];
    }
}
