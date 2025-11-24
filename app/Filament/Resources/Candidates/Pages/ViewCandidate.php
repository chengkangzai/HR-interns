<?php

namespace App\Filament\Resources\Candidates\Pages;

use App\Enums\CandidateStatus;
use App\Filament\Resources\Candidates\CandidateResource;
use App\Filament\Resources\Emails\EmailResource;
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
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property Candidate $record
 */
class ViewCandidate extends ViewRecord
{
    protected static string $resource = CandidateResource::class;

    protected function getActions(): array
    {
        return [
            EditAction::make(),
            Action::make('audit')
                ->url(fn (Candidate $record) => CandidateResource::getUrl('audit', ['record' => $record->id])),
            Action::make('send')
                ->icon('heroicon-o-paper-airplane')
                ->label('Send')
                ->schema([
                    Select::make('mail')
                        ->required()
                        ->reactive()
                        ->options(function () {
                            return Email::where('position_id', $this->record->position_id)
                                ->orderBy('sort')
                                ->pluck('name', 'id');
                        })
                        ->suffixAction(fn (Get $get) => $get('mail') !== null ? Action::make('view_email')
                            ->icon('heroicon-o-eye')
                            ->url(fn () => EmailResource::getUrl('edit', ['record' => $get('mail')]), true)
                            : null
                        ),

                    Section::make('Attachments')
                        ->schema([
                            Select::make('attachments')
                                ->reactive()
                                ->columnSpanFull()
                                ->multiple()
                                ->options(function (Get $get) {
                                    $availableAttachments = [];

                                    // Candidate attachments
                                    $recordAttachments = [
                                        'offer_letters' => 'Offer Letter',
                                        'wfh_letter' => 'WFH Letter',
                                        'completion_letter' => 'Completion Letter',
                                        'attendance_report' => 'Attendance Report',
                                        'completion_cert' => 'Completion Cert',
                                    ];

                                    foreach ($recordAttachments as $key => $label) {
                                        if ($this->record->hasMedia($key)) {
                                            $availableAttachments["record_{$key}"] = "[Candidate] {$label}";
                                        }
                                    }

                                    // Position attachments
                                    $position = $this->record->position;
                                    if ($position && $position->hasMedia('documents')) {
                                        foreach ($position->getMedia('documents') as $document) {
                                            /** @var Media $document */
                                            $availableAttachments["position_{$document->id}"] = "[Position] {$document->name}";
                                        }
                                    }

                                    // Email template attachments
                                    $emailId = $get('mail');
                                    if ($emailId) {
                                        $email = Email::find($emailId);
                                        if ($email && $email->hasMedia('documents')) {
                                            foreach ($email->getMedia('documents') as $document) {
                                                /** @var Media $document */
                                                $availableAttachments["email_{$document->id}"] = "[Email] {$document->name}";
                                            }
                                        }
                                    }

                                    return $availableAttachments;
                                }),
                        ]),

                    Toggle::make('mark_as_contacted')
                        ->default(true)
                        ->visible(fn () => $this->record->status == CandidateStatus::PENDING),
                ])
                ->action(function (array $data, ViewCandidate $livewire) {
                    if (count($data['attachments']) === 0) {
                        $mail = new DefaultMail($this->record, Email::find($data['mail']));
                    } else {
                        $attachments = collect($data['attachments'])
                            ->map(function (string $attachment) use ($data) {
                                [$type, $id] = explode('_', $attachment, 2);

                                return match ($type) {
                                    'record' => $this->record->getFirstMedia($id),
                                    'position' => $this->record->position->getMedia('documents')->firstWhere('id', $id),
                                    'email' => Email::find($data['mail'])->getMedia('documents')->firstWhere('id', $id),
                                    default => null,
                                };
                            })
                            ->filter();

                        $mail = new DefaultMail(
                            candidate: $this->record,
                            email: Email::find($data['mail']),
                            medias: $attachments
                        );
                    }

                    activity()
                        ->performedOn($this->record)
                        ->causedBy(auth()->user())
                        ->event('send_email')
                        ->log('Email requested to be sent to '.$this->record->name.' ('.$this->record->email.')');

                    Mail::to($this->record->email)
                        ->queue($mail);

                    if (data_get($data, 'mark_as_contacted')) {
                        $this->record->update(['status' => CandidateStatus::CONTACTED]);
                    }

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
                    ->schema(self::getOfferLetterForm())
                    ->visible(fn (Candidate $record) => $record->status === CandidateStatus::INTERVIEW)
                    ->disabled(fn (Candidate $record) => $record->position_id == null)
                    ->action(function (Candidate $record, array $data) {
                        dispatch_sync(new GenerateOfferLetterJob($record, $data['pay'], $data['working_from'], $data['working_to']));

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
                        dispatch_sync(new GenerateWFHLetterJob($record));

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
                        dispatch_sync(new GenerateCompletionLetterJob($record));

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
                        dispatch_sync(new GenerateCompletionCertJob($record));

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
                        dispatch_sync(new GenerateAttendanceReportJob($record));

                        Notification::make('generated')
                            ->title('Attendance Report Generated')
                            ->body('The offer letter will be generated in background. Please wait for a while.')
                            ->success()
                            ->send();
                    }),
            ])
                ->dropdownWidth(Width::ExtraSmall->value),
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

                TextEntry::make('working_hours')
                    ->label('Working Hours')
                    ->columnSpanFull()
                    ->state(function (Get $get) {
                        $from = Carbon::parse($get('working_from'));
                        $to = Carbon::parse($get('working_to'));
                        $diff = $from->addHour()->diff($to);

                        return $diff->format('%h hours').' (excluding 1 hour lunch break)';
                    }),
            ]),
        ];
    }
}
