<?php

namespace App\Filament\Resources\CandidateResource\Pages;

use App\Filament\Resources\CandidateResource;
use App\Mail\DefaultMail;
use App\Models\Candidate;
use App\Models\Email;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;

class ViewCandidate extends ViewRecord
{
    protected static string $resource = CandidateResource::class;

    public string|int|null|Model|Candidate $record;

    protected function getActions(): array
    {
        return [
            Action::make('send')
                ->icon('heroicon-o-paper-airplane')
                ->label('Send')
                ->form([
                    Select::make('mail')
                        ->options(Email::pluck('name', 'id'))
                ])
                ->action(function (array $data) {
                    Mail::to($this->record->email)->send(new DefaultMail($this->record, Email::find($data['mail'])));

                    Notification::make()
                        ->title('Email Sent')
                        ->body('The email has been sent to the candidate.')
                        ->success()
                        ->send();
                })
        ];
    }
}
