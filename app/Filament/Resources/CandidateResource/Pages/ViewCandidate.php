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
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
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

                    Toggle::make('include_offer_letter')
                        ->label('Include Offer Letter')
                        ->default(false),
                ])
                ->action(function (array $data, ViewCandidate $livewire) {
                    if ($data['include_offer_letter']) {
                        $media = $this->record->getFirstMedia('offer_letters');
                        if (! $media) {
                            Notification::make()
                                ->title('Offer Letter Not Found')
                                ->body('The offer letter is not found. Please generate/attach the offer letter first.')
                                ->danger()
                                ->send();
                            $livewire->halt();
                        }
                    }

                    if ($data['include_offer_letter']) {
                        $mail = new DefaultMail($this->record, Email::find($data['mail']), $this->record->getMedia('offer_letters'));
                    } else {
                        $mail = new DefaultMail($this->record, Email::find($data['mail']));
                    }

                    activity()
                        ->performedOn($this->record)
                        ->causedBy(auth()->user())
                        ->event('send_email')
                        ->log('Email requested to be sent to '.$this->record->name.' ('.$this->record->email.')');

                    Mail::to($this->record->email)
                        ->send($mail);

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
                        ->prefix('RM')
                        ->default(0),

                    Section::make([
                        TimePicker::make('working_from')
                            ->live()
                            ->label('Working From')
                            ->seconds(false)
                            ->default('09:00'),

                        TimePicker::make('working_to')
                            ->live()
                            ->label('Working To')
                            ->seconds(false)
                            ->default('18:00'),

                        Placeholder::make('working_hours')
                            ->label('Working Hours')
                            ->columnSpanFull()
                            ->content(function (Get $get) {
                                $from = Carbon::parse($get('working_from'));
                                $to = Carbon::parse($get('working_to'));
                                $diff = $from->addHour()->diff($to);

                                return $diff->format('%h hours').' (excluding 1 hour lunch break)';
                            }),
                    ]),
                ])
                ->visible(fn (Candidate $record) => $record->status === CandidateStatus::INTERVIEW)
                ->disabled(fn (Candidate $record) => $record->position_id == null)
                ->action(function (Candidate $record, array $data) {
                    GenerateOfferLetterJob::dispatch($record, $data['pay'], $data['working_from'], $data['working_to']);

                    Notification::make('generated')
                        ->title('Offer Letter Generated')
                        ->body('The offer letter will be generated in background. Please wait for a while.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
