<?php

namespace App\Filament\Resources\CandidateResource\Pages;

use App\Enums\CandidateStatus;
use App\Filament\Resources\CandidateResource;
use App\Jobs\GenerateOfferLetterJob;
use App\Mail\DefaultMail;
use App\Models\Candidate;
use App\Models\Email;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
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
            EditAction::make(),
            Action::make('send')
                ->icon('heroicon-o-paper-airplane')
                ->label('Send')
                ->form([
                    Select::make('mail')
                        ->options(Email::pluck('name', 'id')),
                ])
                ->action(function (array $data) {
                    Mail::to($this->record->email)->send(new DefaultMail($this->record, Email::find($data['mail'])));

                    Notification::make()
                        ->title('Email Sent')
                        ->body('The email has been sent to the candidate.')
                        ->success()
                        ->send();
                }),

            Action::make('generate_offer_letter')
                ->icon('heroicon-o-document')
                ->label('Generate Offer Letter')
                ->form([
                    TextInput::make('pay')
                        ->numeric()
                        ->label('Pay')
                        ->prefix('RM'),
                ])
                ->visible(fn (Candidate $record) => $record->status === CandidateStatus::INTERVIEW)
                ->disabled(fn (Candidate $record) => $record->position_id == null)
                ->action(function (Candidate $record, array $data) {
                    GenerateOfferLetterJob::dispatch($record, $data['pay']);

                    Notification::make('generated')
                        ->title('Offer Letter Generated')
                        ->body('The offer letter will be generated in background. Please wait for a while.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
