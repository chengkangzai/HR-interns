<?php

namespace App\Filament\Resources;

use App\Enums\CandidateStatus;
use App\Enums\PositionStatus;
use App\Filament\Resources\CandidateResource\Pages;
use App\Filament\Resources\PositionResource\Pages\ViewPositionCandidate;
use App\Jobs\GenerateOfferLetterJob;
use App\Jobs\SendEmailJob;
use App\Models\Candidate;
use App\Models\Email;
use App\Models\Position;
use App\Services\PdfExtractorService;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\SpatieTagsColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\PdfToText\Pdf;
use Spatie\Tags\Tag;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;

class CandidateResource extends Resource
{
    protected static ?string $model = Candidate::class;

    protected static ?string $slug = 'candidates';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make([
                TextInput::make('name')
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (string $state, Set $set) => $set('name', str($state)->title()->__toString()))
                    ->required(),

                TextInput::make('email')
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $state, Set $set) {
                        $set('email', str($state)->remove(' ')->remove('`'));
                    })
                    ->suffixAction(fn (?string $state) => FormAction::make('Email')
                        ->icon('heroicon-o-envelope-open')
                        ->tooltip('Send Email')
                        ->url('mailto:'.$state, true)
                    )
                    ->required(),

                PhoneInput::make('phone_number')
                    ->suffixAction(fn (?string $state) => FormAction::make('WhatsApp')
                        ->icon('heroicon-o-phone-arrow-up-right')
                        ->tooltip('Send WhatsApp Message')
                        ->url('https://wa.me/'.str_replace(['+', ' ', '(', ')', '-'], '', $state), true)
                    )
                    ->formatOnDisplay(true),

                SpatieTagsInput::make('tags'),
            ]),

            Section::make([
                Select::make('position_id')
                    ->suffixAction(function (string $context, ?Candidate $record) {
                        if (! $record) {
                            return null;
                        }
                        if ($context == 'create') {
                            return null;
                        }

                        return FormAction::make('view_position')
                            ->icon('heroicon-o-eye')
                            ->url(PositionResource::getUrl('view', ['record' => $record->position_id]), true);
                    })
                    ->relationship('position', 'title')
                    ->options(fn () => Position::query()
                        ->where('status', PositionStatus::OPEN)
                        ->get()
                        ->groupBy('type')
                        ->map(function ($positions) {
                            return $positions->pluck('title', 'id');
                        })
                        ->toArray()
                    )
                    ->createOptionForm([
                        TextInput::make('title')
                            ->required(),
                    ])
                    ->required(),

                Select::make('status')
                    ->options(CandidateStatus::class)
                    ->default(CandidateStatus::PENDING)
                    ->required(),
            ]),

            Section::make([
                DatePicker::make('from')
                    ->live(onBlur: true),

                DatePicker::make('to')
                    ->live(onBlur: true),

                Placeholder::make('range')
                    ->label('From - To')
                    ->visibleOn(['view', 'edit'])
                    ->content(fn (Get $get): string => $get('from') !== null && $get('to') !== null
                        ? ceil(Carbon::parse($get('from'))->floatDiffInWeeks(Carbon::parse($get('to')))).' weeks'
                        : 'N/A'
                    ),
            ]),

            Section::make([
                SpatieMediaLibraryFileUpload::make('resume')
                    ->hintActions([
                        FormAction::make('extract-text')
                            ->hidden(fn (string $context) => $context == 'create')
                            ->icon('heroicon-o-document-text')
                            ->visible(fn (Candidate $record) => $record->getFirstMedia('resumes') !== null)
                            ->modalSubmitAction(
                                \Filament\Actions\Action::make('Copy Text_Close')
                                    ->label('Copy Text & Close')
                                    ->extraAttributes([
                                        'x-on:click' => new HtmlString('navigator.clipboard.writeText(document.getElementById(\'pdf-content\').innerText) && new FilamentNotification().success().title(\'Copied !\').send() && close'),
                                    ])
                            )
                            ->modalCancelAction(false)
                            ->form([
                                Placeholder::make('text')
                                    ->content(function (Candidate $record): HtmlString {
                                        $pdfPath = $record->getFirstMedia('resumes')->getPath();
                                        $pdfText = (new Pdf)->setPdf($pdfPath)->text();

                                        return new HtmlString(<<<HTML
                            <div class="flex flex-col relative">
                                <div class="max-h-96 overflow-y-auto p-4 bg-gray-50 rounded-lg whitespace-pre-wrap" id="pdf-content">
                                    {$pdfText}
                                </div>
                            </div>
                        HTML);
                                    }),
                            ]),
                    ])
                    ->afterStateUpdated(function (TemporaryUploadedFile $state, Set $set, Get $get, string $context) {
                        if ($context !== 'create') {
                            return;
                        }
                        try {

                            $extractor = app(PdfExtractorService::class)->extractInformation($state->path());

                            // Set personal information if available
                            if (isset($extractor['personal_info'])) {
                                $personalInfo = $extractor['personal_info'];

                                // Set name if empty
                                if ($personalInfo['name']) {
                                    $set('name', str($personalInfo['name'])->title()->__toString());
                                }

                                // Set email if empty
                                if ($personalInfo['email']) {
                                    $set('email', str($personalInfo['email'])->remove(' ')->remove('`')->__toString());
                                }

                                // Set phone number if empty
                                if ($personalInfo['phone_number']) {
                                    $set('phone_number', $personalInfo['phone_number']);
                                }
                            }

                            // Initialize additional info array
                            $additionalInfo = [];

                            // Add qualifications if available
                            if (isset($extractor['qualifications']) && is_array($extractor['qualifications'])) {
                                foreach ($extractor['qualifications'] as $qualificationEntry) {
                                    if (! isset($qualificationEntry['data'])) {
                                        continue;
                                    }

                                    $qualification = $qualificationEntry['data'];

                                    // Create qualification block
                                    $qualificationBlock = [
                                        'type' => 'qualification',
                                        'data' => [
                                            'qualification' => $qualification['qualification'] ?? null,
                                            'major' => str($qualification['major'] ?? '')->title()->__toString(),
                                            'university' => str($qualification['university'] ?? '')->trim()->title()->__toString(),
                                            'gpa' => $qualification['gpa'] ?? null,
                                            'from' => $qualification['from'] ?? null,
                                            'to' => $qualification['to'] ?? null,
                                        ],
                                    ];

                                    // Add qualification block to additional info
                                    $additionalInfo[] = $qualificationBlock;
                                }
                            }

                            // Preserve any existing non-qualification blocks in additional_info
                            $existingBlocks = $get('additional_info') ?? [];
                            if (is_array($existingBlocks)) {
                                foreach ($existingBlocks as $block) {
                                    // Only keep non-qualification blocks
                                    if (isset($block['type']) && $block['type'] !== 'qualification') {
                                        $additionalInfo[] = $block;
                                    }
                                }
                            }

                            if (isset($extractor['social_media']) && is_array($extractor['social_media'])) {
                                foreach ($extractor['social_media'] as $socialMediaEntry) {
                                    if (! isset($socialMediaEntry['data'])) {
                                        continue;
                                    }

                                    $socialMedia = $socialMediaEntry['data'];

                                    // Create social media block
                                    $socialMediaBlock = [
                                        'type' => 'social_media',
                                        'data' => [
                                            'social_media' => $socialMedia['social_media'] ?? null,
                                            'username' => $socialMedia['username'] ?? null,
                                            'url' => $socialMedia['url'] ?? null,
                                        ],
                                    ];

                                    // Add social media block to additional info
                                    $additionalInfo[] = $socialMediaBlock;
                                }
                            }

                            // Set the additional_info field with the updated array
                            if (! empty($additionalInfo)) {
                                $set('additional_info', $additionalInfo);
                            }

                            // Show notification based on what was extracted
                            if (! empty($extractedInfo)) {
                                Notification::make()
                                    ->title('Resume Information Extracted')
                                    ->body('Successfully extracted '.implode(' and ', $extractedInfo).'.')
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Resume Processing')
                                    ->body('No information could be extracted from the resume. Please fill in the information manually.')
                                    ->warning()
                                    ->send();
                            }
                        } catch (\Exception) {
                            Notification::make()
                                ->title('Error Occurred')
                                ->body('Time to manually extract resume information!')
                                ->danger()
                                ->send();
                        }

                    })
                    ->label('Resume')
                    ->collection('resumes')
                    ->acceptedFileTypes(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']),

                SpatieMediaLibraryFileUpload::make('offer_letter')
                    ->multiple()
                    ->hiddenOn('create')
                    ->label('Offer Letter')
                    ->collection('offer_letters')
                    ->acceptedFileTypes(['application/pdf']),

                SpatieMediaLibraryFileUpload::make('documents')
                    ->label('Other Documents')
                    ->collection('other_documents')
                    ->multiple(),

                SpatieMediaLibraryFileUpload::make('wfh_letter')
                    ->label('WFH Letter')
                    ->collection('wfh_letter')
                    ->hiddenOn('create')
                    ->multiple(),

                SpatieMediaLibraryFileUpload::make('completion_letter')
                    ->label('Completion Letter')
                    ->collection('completion_letter')
                    ->hiddenOn('create')
                    ->multiple(),

                SpatieMediaLibraryFileUpload::make('completion_cert')
                    ->label('Completion Certificate')
                    ->collection('completion_cert')
                    ->hiddenOn('create')
                    ->multiple(),

                SpatieMediaLibraryFileUpload::make('attendance_report')
                    ->label('Attendance Letter')
                    ->collection('attendance_report')
                    ->hiddenOn('create')
                    ->multiple(),
            ]),

            Section::make([
                Builder::make('additional_info')
                    ->label('Additional Information')
                    ->blocks([
                        Builder\Block::make('source')
                            ->icon('heroicon-o-link')
                            ->schema([
                                Select::make('source')
                                    ->options([
                                        'LinkedIn' => 'LinkedIn',
                                        'Indeed' => 'Indeed',
                                        'Referral' => 'Referral',
                                        'Email' => 'Email',
                                        'Others' => 'Others',
                                    ])
                                    ->required(),

                                TextInput::make('other_source')
                                    ->visible(fn (Get $get) => $get('source') === 'Others'),
                            ])
                            ->columns(2),

                        Builder\Block::make('qualification')
                            ->icon('heroicon-o-academic-cap')
                            ->schema([
                                Select::make('qualification')
                                    ->options([
                                        'Diploma' => 'Diploma',
                                        'Bachelor' => 'Bachelor',
                                        'Master' => 'Master',
                                        'PhD' => 'PhD',
                                        'Others' => 'Others',
                                    ]),
                                TextInput::make('major')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (?string $state, Set $set) {
                                        if ($state) {
                                            $set('major', str($state)->title());
                                        }
                                    })
                                    ->prefix('in '),
                                TextInput::make('university')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (?string $state, Set $set) {
                                        if ($state) {
                                            $set('university', str($state)->trim()->title());
                                        }
                                    })
                                    ->prefix('from '),
                                TextInput::make('gpa')
                                    ->prefix('with GPA '),
                                Fieldset::make('from_to')
                                    ->label('From - To')
                                    ->columns(2)
                                    ->schema([
                                        DatePicker::make('from'),
                                        DatePicker::make('to'),
                                    ]),
                            ])
                            ->columns(2),

                        Builder\Block::make('social_media')
                            ->icon('heroicon-o-globe-alt')
                            ->schema([
                                Select::make('social_media')
                                    ->required()
                                    ->options([
                                        'linkedin' => 'LinkedIn',
                                        'github' => 'GitHub',
                                        'twitter' => 'Twitter',
                                        'facebook' => 'Facebook',
                                        'instagram' => 'Instagram',
                                        'others' => 'Others',
                                    ]),

                                Fieldset::make('Info')
                                    ->schema([
                                        TextInput::make('url')
                                            ->suffixAction(
                                                FormAction::make('view')
                                                    ->icon('heroicon-o-eye')
                                                    ->url(fn (?string $state) => $state, true)
                                            ),

                                        TextInput::make('username')
                                            ->reactive()
                                            ->afterStateUpdated(function (Get $get, Set $set) {
                                                match ($get('social_media')) {
                                                    'linkedin' => $set('url', 'https://www.linkedin.com/in/'.$get('username')),
                                                    'github' => $set('url', 'https://github.com/'.$get('username')),
                                                    'twitter' => $set('url', 'https://twitter.com/'.$get('username')),
                                                    'facebook' => $set('url', 'https://www.facebook.com/'.$get('username')),
                                                    'instagram' => $set('url', 'https://www.instagram.com/'.$get('username')),
                                                    default => null,
                                                };
                                            }),
                                    ]),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpanFull(),
            ]),

            RichEditor::make('notes')
                ->columnSpanFull(),

            Placeholder::make('created_at')
                ->label('Created Date')
                ->content(fn (?Candidate $record): string => $record?->created_at?->diffForHumans() ?? '-'),

            Placeholder::make('updated_at')
                ->label('Last Modified Date')
                ->content(fn (?Candidate $record): string => $record?->updated_at?->diffForHumans() ?? '-'),

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable(),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->url(fn (Candidate $record) => 'mailto:'.$record->email, true)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('phone_number')
                    ->url(fn (Candidate $record) => 'https://wa.me/'.str_replace(['+', ' ', '(', ')', '-'], '', $record->phone_number), true)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->fontFamily(FontFamily::Mono),

                TextColumn::make('range')
                    ->toggleable()
                    ->sortable(query: fn (EloquentBuilder $query, string $direction) => $query->orderBy('from', $direction))
                    ->label('From - To')
                    ->getStateUsing(fn (Candidate $record) => isset($record->from, $record->to)
                        ? $record->from->format('d/m/Y').' - '.$record->to->format('d/m/Y').' ('.ceil($record->from->floatDiffInWeeks($record->to)).' weeks)'
                        : 'N/A'
                    ),

                TextColumn::make('position.title')
                    ->hidden(fn ($livewire) => $livewire instanceof ViewPositionCandidate),

                TextColumn::make('from')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->date(),

                TextColumn::make('to')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->date(),

                TextColumn::make('status')
                    ->badge(),

                TextColumn::make('education')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->getStateUsing(function (Candidate $record) {
                        if (! $record->additional_info) {
                            return '-';
                        }

                        $qualification = $record->additional_info->where('type', 'qualification')->value('data');
                        if (! $qualification) {
                            return '-';
                        }

                        return $qualification['university'];
                    })
                    ->tooltip(function (Candidate $record) {
                        if (! $record->additional_info) {
                            return '-';
                        }

                        $qualification = $record->additional_info->where('type', 'qualification')->value('data');
                        if (! $qualification) {
                            return '-';
                        }

                        $parts = collect([
                            Str::of($qualification['qualification'])->whenNotEmpty(fn ($str) => $str->wrap('[', ']')),
                            Str::of($qualification['major'])->whenNotEmpty(fn ($str) => $str->wrap('[', ']')->prepend('in ')),
                            Str::of($qualification['university'])->whenNotEmpty(fn ($str) => $str->wrap('[', ']')->prepend('at ')),
                            Str::of($qualification['gpa'])->whenNotEmpty(fn ($str) => $str->wrap('[', ']')->prepend('with GPA of ')),
                        ])->filter()->values();

                        return new HtmlString(
                            $parts->isEmpty() ? '-' : $parts->join(' ')
                        );
                    }),

                SpatieTagsColumn::make('tags')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(CandidateStatus::class)
                    ->multiple()
                    ->label('Status'),

                SelectFilter::make('position_id')
                    ->searchable()
                    ->preload()
                    ->relationship('openPosition', 'title', function ($query) {
                        $query->whereHas('candidates');
                    })
                    ->label('Position'),

                SelectFilter::make('tags')
                    ->relationship('tags', 'name')
                    ->multiple()
                    ->preload()
                    ->getOptionLabelFromRecordUsing(fn (Tag $record) => $record->name),

                TrashedFilter::make(),
            ])
            ->recordUrl(fn (Candidate $record) => CandidateResource::getUrl('view', ['record' => $record]))
            ->actions([
                Action::make('status')
                    ->color(Color::Blue)
                    ->icon('heroicon-s-check-circle')
                    ->form([
                        Select::make('status')
                            ->options(CandidateStatus::class),
                    ])
                    ->action(fn (Candidate $record, array $data) => $record->update($data)),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    self::getAddTagsBulkAction(),

                    self::getRemoveTagsBulkAction(),
                ])
                    ->label('Tags')
                    ->icon('heroicon-s-tag'),

                BulkActionGroup::make([
                    BulkAction::make('change_status')
                        ->icon('heroicon-s-check-circle')
                        ->form([
                            Select::make('status')
                                ->options(CandidateStatus::class),
                        ])
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records, array $data) {
                            $records->each(fn (Candidate $record) => $record->update([
                                'status' => $data['status'],
                            ]));
                        }),
                    BulkAction::make('change_position')
                        ->visible(fn (Page $livewire) => $livewire instanceof Pages\ListCandidates)
                        ->icon('heroicon-s-check-circle')
                        ->form([
                            Select::make('position')
                                ->relationship('position', 'title', fn (EloquentBuilder $query) => $query->where('status', PositionStatus::OPEN)),
                        ])
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records, array $data) {
                            $records->each(fn (Candidate $record) => $record->update([
                                'position_id' => $data['position'],
                            ]));
                        }),
                ])
                    ->label('Edit')
                    ->icon('heroicon-s-pencil'),

                DeleteBulkAction::make()
                    ->label('Delete'),
                BulkAction::make('send_email')
                    ->icon('heroicon-o-envelope-open')
                    ->form(fn (Collection $records) => [
                        Select::make('email')
                            ->live()
                            ->options(function ($livewire) use ($records) {
                                if ($livewire instanceof ViewPositionCandidate) {
                                    /** @var Position $record */
                                    $record = $livewire->record;

                                    return Email::where('position_id', $record->id)
                                        ->orderBy('sort')
                                        ->pluck('name', 'id');
                                }

                                return Email::whereIn('position_id', $records->pluck('position_id')->toArray())
                                    ->orderBy('sort')
                                    ->pluck('name', 'id');
                            })
                            ->suffixAction(fn (Get $get) => $get('email') !== null ? FormAction::make('view_email')
                                ->icon('heroicon-o-eye')
                                ->url(fn () => EmailResource::getUrl('edit', ['record' => $get('email')]), true)
                                : null
                            ),
                    ])
                    ->action(function (Collection $records, array $data) {
                        $email = Email::find($data['email']);
                        $records->each(function (Candidate $record, $index) use ($email) {
                            activity()
                                ->performedOn($record)
                                ->causedBy(auth()->user())
                                ->event('send_email')
                                ->log('Email requested to be sent to '.$record->name.' ('.$record->email.')');

                            SendEmailJob::dispatch($email, $record)->delay(now()->addSeconds($index * 30));
                        });

                        Notification::make()
                            ->success()
                            ->title('Email Sent')
                            ->body('Email has been sent to '.$records->count().' candidate(s). <br>'.
                                'ETA: <b>'.now()->addSeconds($records->count() * 30)->shortRelativeDiffForHumans().'</b>'
                            )
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(true),
                BulkAction::make('generate_offer_letter')
                    ->icon('heroicon-o-document')
                    ->deselectRecordsAfterCompletion()
                    ->form(Pages\ViewCandidate::getOfferLetterForm())
                    ->action(function (Collection $records, array $data) {
                        $candidates = $records
                            ->reject(fn (Candidate $record) => $record->status === CandidateStatus::INTERVIEW);

                        if ($candidates->count() > 0) {
                            Notification::make()
                                ->title('Invalid Candidates')
                                ->body('The following candidates are not in interview status: <br>'.
                                    $candidates->map(fn (Candidate $candidate) => $candidate->name.' ('.$candidate->email.')')->join('<br>')
                                    .'<br>Please change their status to interview first.'
                                )
                                ->danger()
                                ->send();

                            return;
                        }

                        $records->filter(fn (Candidate $record) => $record->getMedia('offer_letters')->count() === 0)// only generate offer letter for candidates that don't have offer letter yet
                            ->each(fn (Candidate $record) => GenerateOfferLetterJob::dispatch($record, $data['pay'], $data['working_from'], $data['working_to']));

                        Notification::make()
                            ->title('Offer Letter Generated')
                            ->body('The offer letter will be generated in background. Please wait for a while.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCandidates::route('/'),
            'create' => Pages\CreateCandidate::route('/create'),
            'view' => Pages\ViewCandidate::route('/{record}/'),
            'edit' => Pages\EditCandidate::route('/{record}/edit'),
            'audit' => Pages\AuditCandidate::route('/{record}/audit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email'];
    }

    private static function getAddTagsBulkAction(): BulkAction
    {
        return BulkAction::make('add_tags')
            ->icon('heroicon-s-tag')
            ->color('success')
            ->form([
                SpatieTagsInput::make('tags'),
            ])
            ->action(function (Collection $records, array $data, $livewire) {
                $records->each(fn (Candidate $customer) => $customer->attachTags(
                    tags: $livewire->mountedTableBulkActionData['tags']
                ));

                Notification::make('success')
                    ->success()
                    ->title('Tags updated successfully.')
                    ->body('The selected candidates have been updated.')
                    ->send();
            });
    }

    private static function getRemoveTagsBulkAction(): BulkAction
    {
        return BulkAction::make('remove_tags')
            ->icon('heroicon-s-tag')
            ->color('danger')
            ->form(fn ($records) => [
                Select::make('tags')
                    ->multiple()
                    ->options(fn () => Tag::query()
                        ->pluck('name')
                        ->mapWithKeys(fn ($tag) => [$tag => $tag])
                    ),
            ])
            ->action(function (Collection $records, array $data) {
                if (! isset($data['tags'])) {
                    return;
                }
                $records->each(fn (Candidate $candidate) => $candidate->detachTags(
                    tags: $data['tags']
                ));

                Notification::make('success')
                    ->success()
                    ->title('Tags updated successfully.')
                    ->body('The selected candidates have been updated.')
                    ->send();
            });
    }
}
