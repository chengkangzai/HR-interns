<?php

namespace App\Filament\Resources\CandidateResource\Pages;

use App\Enums\CandidateStatus;
use App\Filament\Resources\CandidateResource;
use App\Filament\Resources\EmailResource;
use App\Jobs\GenerateAttendanceReportJob;
use App\Jobs\GenerateCompletionCertJob;
use App\Jobs\GenerateCompletionLetterJob;
use App\Jobs\GenerateOfferLetterJob;
use App\Jobs\GenerateWFHLetterJob;
use App\Mail\DefaultMail;
use App\Models\Candidate;
use App\Models\Email;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
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
            Action::make('audit')
                ->url(fn (Candidate $record) => CandidateResource::getUrl('audit', ['record' => $record->id])),
            Action::make('send')
                ->icon('heroicon-o-paper-airplane')
                ->label('Send')
                ->form([
                    Select::make('mail')
                        ->required()
                        ->reactive()
                        ->options(function () {
                            return Email::where('position_id', $this->record->position_id)
                                ->orderBy('sort')
                                ->pluck('name', 'id');
                        })
                        ->suffixAction(fn (Get $get) => $get('mail') !== null ? FormAction::make('view_email')
                            ->icon('heroicon-o-eye')
                            ->url(fn () => EmailResource::getUrl('edit', ['record' => $get('mail')]), true)
                            : null
                        ),

                    Section::make('Attachments')
                        ->schema([
                            Select::make('attachments')
                                ->multiple()
                                ->options([
                                    'offer_letters' => 'Offer Letter',
                                    'wfh_letter' => 'WFH Letter',
                                    'completion_letter' => 'Completion Letter',
                                    'attendance_report' => 'Attendance Report',
                                ]),
                        ]),
                ])
                ->action(function (array $data, ViewCandidate $livewire) {
                    if (count($data['attachments']) !== 0) {
                        $notFound = false;
                        foreach ($data['attachments'] as $attachment) {
                            $media = $this->record->getFirstMedia($attachment);
                            if (! $media) {
                                $name = str($attachment)->replace('_', ' ')->title();
                                Notification::make()
                                    ->title($name.' Not Found')
                                    ->body('The '.$name.' is not found. Please generate/attach the '.$name.' first.')
                                    ->danger()
                                    ->send();
                                $notFound = true;
                            }
                        }
                        if ($notFound === true) {
                            $livewire->halt();
                        }
                    }

                    if (count($data['attachments']) !== 0) {
                        $attachments = collect($data['attachments'])
                            ->map(fn (string $attachment) => $this->record->getMedia($attachment));
                        $mail = new DefaultMail($this->record, Email::find($data['mail']), $attachments);
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

            ActionGroup::make([
                Action::make('generate_offer_letter')
                    ->icon('heroicon-o-document')
                    ->label('Generate Offer Letter')
                    ->form(self::getOfferLetterForm())
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

                Action::make('generate_wfh')
                    ->icon('heroicon-o-document')
                    ->label('Generate WFH Letter')
                    ->disabled(fn (Candidate $record) => $record->position_id == null)
                    ->action(function (Candidate $record) {
                        GenerateWFHLetterJob::dispatch($record);

                        Notification::make('generated')
                            ->title('WFH Letter Generating')
                            ->body('The WFH letter will be generating in background. Please wait for a while.')
                            ->success()
                            ->send();
                    }),

                Action::make('generate_completion_letter')
                    ->icon('heroicon-o-document')
                    ->label('Generate Completion Letter')
                    ->disabled(fn (Candidate $record) => $record->position_id == null)
                    ->action(function (Candidate $record) {
                        GenerateCompletionLetterJob::dispatch($record);

                        Notification::make('generated')
                            ->title('Completion Letter Generating')
                            ->body('The Completion Letter will be generating in background. Please wait for a while.')
                            ->success()
                            ->send();
                    }),

                Action::make('generate_completion_cert')
                    ->icon('heroicon-o-document')
                    ->label('Generate Completion Cert')
                    ->disabled(fn (Candidate $record) => $record->position_id == null)
                    ->action(function (Candidate $record, $livewire) {
                        GenerateCompletionCertJob::dispatch($record);

                        Notification::make('generated')
                            ->title('Completion Cert Generating')
                            ->body('The Completion Cert will be generating in background. Please wait for a while.')
                            ->success()
                            ->send();

                        $livewire->dispatch('refresh');
                    }),

                Action::make('generate_attendance_report')
                    ->icon('heroicon-o-document')
                    ->label('Generate Attendance Report')
                    ->visible(fn (Candidate $record) => $record->status === CandidateStatus::COMPLETED)
                    ->action(function (Candidate $record) {
                        GenerateAttendanceReportJob::dispatch($record);

                        Notification::make('generated')
                            ->title('Attendance Report Generated')
                            ->body('The offer letter will be generated in background. Please wait for a while.')
                            ->success()
                            ->send();
                    }),
            ]),
        ];
    }

    public static function getOfferLetterForm(): array
    {
        return [
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
        ];
    }
}
