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
                        ->default(auth()->user()->email)
                        ->required(),
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
