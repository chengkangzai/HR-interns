<?php

namespace App\Filament\Resources\EmailResource\Pages;

use App\Filament\Resources\EmailResource;
use App\Mail\DefaultMail;
use App\Models\Candidate;
use App\Models\Email;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;

class EditEmail extends EditRecord
{
    protected static string $resource = EmailResource::class;

    /**
     * @var Email|null
     */
    public string|int|null|Model $record = null;

    protected function getActions(): array
    {
        return [
            Action::make('Send Preview Email')
                ->icon('heroicon-o-eye')
                ->form([
                    TextInput::make('email')
                        ->email()
                        ->required(),
                ])
                ->action(function (array $data) {
                    $candidate = Candidate::create([
                        'name' => 'Participant',
                        'email' => $data['email'],
                        'phone_number' => '0123456789',
                    ]);
                    Mail::to($data['email'])
                        ->send(new DefaultMail($candidate, $this->record));

                    $candidate->delete();
                    Notification::make()
                        ->success()
                        ->title('Email Sent')
                        ->body('Email sent successfully.')
                        ->send();
                })
            ,
            DeleteAction::make(),
        ];
    }
}
