<?php

namespace App\Filament\Resources\EmailResource\Pages;

use App\Enums\CandidateStatus;
use App\Filament\Resources\EmailResource;
use App\Mail\DefaultMail;
use App\Models\Candidate;
use App\Models\Email;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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
                        ->default(auth()->user()->email)
                        ->required(),

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
                ->action(function (array $data) {
                    $candidate = Candidate::firstOrCreate([
                        'name' => 'Participant',
                        'email' => $data['email'],
                        'phone_number' => '+60123456789',
                    ]);
                    if (count($data['attachments']) === 0) {
                        $mail = new DefaultMail($candidate, Email::find($data['mail']));
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
                            candidate: $candidate,
                            email: Email::find($data['mail']),
                            medias: $attachments
                        );
                    }

                    Mail::to($mail)
                        ->send(new DefaultMail($candidate, $this->record));

                    $candidate->delete();
                    Notification::make()
                        ->success()
                        ->title('Email Sent')
                        ->body('Email sent successfully.')
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
