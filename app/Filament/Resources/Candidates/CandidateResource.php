<?php

namespace App\Filament\Resources\Candidates;

use App\Filament\Resources\Candidates\CandidateResource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Actions\Action;
use Filament\Schemas\Components\Utilities\Get;
use Exception;
use App\Filament\Resources\Candidates\Pages\ViewCandidate;
use Filament\Schemas\Components\Fieldset;
use Filament\Forms\Components\Builder\Block;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\BulkAction;
use App\Filament\Resources\Candidates\Pages\ListCandidates;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\Candidates\Pages\CreateCandidate;
use App\Filament\Resources\Candidates\Pages\EditCandidate;
use App\Filament\Resources\Candidates\Pages\AuditCandidate;
use App\Enums\CandidateStatus;
use App\Enums\PositionStatus;
use App\Enums\PositionType;
use App\Filament\Resources\CandidateResource\Pages;
use App\Filament\Resources\Positions\Pages\ViewPositionCandidate;
use App\Jobs\GenerateAttendanceReportJob;
use App\Jobs\GenerateCompletionCertJob;
use App\Jobs\GenerateCompletionLetterJob;
use App\Jobs\GenerateOfferLetterJob;
use App\Jobs\GenerateWFHLetterJob;
use App\Mail\DefaultMail;
use App\Models\Candidate;
use App\Models\Email;
use App\Models\Position;
use App\Services\PdfExtractorService;
use Carbon\CarbonInterface;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\SpatieTagsColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\PdfToText\Pdf;
use Spatie\Tags\Tag;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;

class CandidateResource extends Resource
{
    protected static ?string $model = Candidate::class;

    protected static ?string $slug = 'candidates';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-user-group';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make([
                TextInput::make('name')
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (string $state, Set $set) => $set('name', str($state)->title()))
                    ->required(),

                TextInput::make('email')
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $state, Set $set) {
                        $set('email', str($state)->remove(' ')->remove('`'));
                    })
                    ->suffixAction(fn (?string $state) => Action::make('Email')
                        ->icon('heroicon-o-envelope-open')
                        ->tooltip('Send Email')
                        ->url('mailto:'.$state, true)
                    )
                    ->required(),

                PhoneInput::make('phone_number')
                    ->suffixAction(fn (?string $state) => Action::make('WhatsApp')
                        ->icon('heroicon-o-phone-arrow-up-right')
                        ->tooltip('Send WhatsApp Message')
                        ->url('https://wa.me/'.str_replace(['+', ' ', '(', ')', '-'], '', $state), true)
                    )
                    ->formatOnDisplay(true),

                SpatieTagsInput::make('tags')
                    ->type('default'),

                SpatieTagsInput::make('skills')
                    ->type('skills'),
            ]),

            Section::make([
                Select::make('position_id')
                    ->live()
                    ->suffixAction(function (string $context, ?Candidate $record) {
                        if (! $record) {
                            return null;
                        }
                        if ($context == 'create') {
                            return null;
                        }

                        return Action::make('view_position')
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
                ])->compact()
                    ->visible(fn (Get $get) => Position::find($get('position_id'))?->type !== PositionType::FULL_TIME),
            ]),

            Section::make([
                SpatieMediaLibraryFileUpload::make('resume')
                    ->hintActions([
                        Action::make('Auto-Fill from Resume')
                            ->icon('heroicon-o-pencil-square')
                            ->visible(fn (?Candidate $record, string $context) => $context == 'edit' && $record->getFirstMedia('resumes') !== null)
                            ->action(function (Candidate $record, Set $set, Get $get) {
                                try {
                                    $s3Url = $record->getFirstMedia('resumes')->getTemporaryUrl(now()->addMinutes(5));
                                    $tempPath = storage_path('app/'.uniqid().'.pdf');

                                    file_put_contents($tempPath, file_get_contents($s3Url));

                                    $extractedInfo = CandidateResource::extractAndFillResumeInformation($tempPath, $set, $get);

                                    unlink($tempPath);

                                    if (! empty($extractedInfo)) {
                                        Notification::make()
                                            ->title('Information Extracted')
                                            ->body('Successfully extracted '.implode(', ', $extractedInfo).'.')
                                            ->success()
                                            ->send();
                                    }
                                } catch (Exception $e) {
                                    Notification::make()
                                        ->title('Error Occurred')
                                        ->body('Failed to extract resume information: '.$e->getMessage())
                                        ->danger()
                                        ->send();
                                }
                            }),
                        Action::make('extract-text')
                            ->icon('heroicon-o-document-text')
                            ->visible(fn (?Candidate $record, string $context) => $context == 'edit' && $record->getFirstMedia('resumes') !== null)
                            ->modalSubmitAction(
                                Action::make('Copy Text_Close')
                                    ->label('Copy Text & Close')
                                    ->extraAttributes([
                                        'x-on:click' => new HtmlString('navigator.clipboard.writeText(document.getElementById(\'pdf-content\').innerText) && new FilamentNotification().success().title(\'Copied !\').send() && close'),
                                    ])
                            )
                            ->modalCancelAction(false)
                            ->schema([
                                Placeholder::make('text')
                                    ->content(function (Candidate $record): HtmlString {// Get temporary S3 URL valid for 5 minutes
                                        $s3Url = $record->getFirstMedia('resumes')->getTemporaryUrl(now()->addMinutes(5));

                                        // Create temp file path
                                        $tempPath = storage_path('app/'.uniqid().'.pdf');

                                        // Download file from S3
                                        file_put_contents($tempPath, file_get_contents($s3Url));

                                        $pdfText = (new Pdf)
                                            ->setPdf($tempPath)
                                            ->text();

                                        unlink($tempPath); // Clean up temp file

                                        return new HtmlString(<<<HTML
                            <div class="flex flex-col relative">
                                <div class="max-h-96 overflow-y-auto p-4 bg-gray-50 dark:bg-gray-800 rounded-lg whitespace-pre-wrap dark:text-gray-200" id="pdf-content">
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
                            $extractedInfo = CandidateResource::extractAndFillResumeInformation($state->path(), $set, $get);

                            if (! empty($extractedInfo)) {
                                Notification::make()
                                    ->title('Resume Information Extracted')
                                    ->body('Successfully extracted '.implode(' and ', $extractedInfo).'.')
                                    ->success()
                                    ->send();
                            }
                        } catch (Exception $e) {
                            info($e);
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
                    ->hintActions([
                        Action::make('generate_offer_letter')
                            ->icon('heroicon-o-document')
                            ->label('Generate')
                            ->schema(ViewCandidate::getOfferLetterForm())
                            ->visible(fn (?Candidate $record) => $record->status === CandidateStatus::INTERVIEW)
                            ->action(function (?Candidate $record, array $data) {
                                dispatch_sync(new GenerateOfferLetterJob($record, $data['pay'], $data['working_from'], $data['working_to']));
                                Notification::make('generated')
                                    ->title('Generating')
                                    ->body('It will be generated in background. Please wait and refresh the page.')
                                    ->success()
                                    ->send();
                            }),
                    ])
                    ->multiple()
                    ->hiddenOn('create')
                    ->label('Offer Letter')
                    ->collection('offer_letters')
                    ->acceptedFileTypes(['application/pdf']),

                SpatieMediaLibraryFileUpload::make('documents')
                    ->label('Other Documents')
                    ->collection('other_documents')
                    ->multiple(),

                Section::make([
                    SpatieMediaLibraryFileUpload::make('wfh_letter')
                        ->hintActions([
                            Action::make('generate_wfh')
                                ->icon('heroicon-o-document')
                                ->visible(fn (?Candidate $record) => $record->getFirstMedia('wfh_letter') !== null)
                                ->label('Generate')
                                ->action(function (?Candidate $record) {
                                    dispatch_sync(new GenerateWFHLetterJob($record));
                                    Notification::make('generated')
                                        ->title('Generating')
                                        ->body('It will be generated in background. Please wait and refresh the page.')
                                        ->success()
                                        ->send();
                                }),
                        ])
                        ->label('WFH Letter')
                        ->collection('wfh_letter')
                        ->hiddenOn('create')
                        ->multiple(),

                    SpatieMediaLibraryFileUpload::make('completion_letter')
                        ->hintActions([
                            Action::make('generate_completion_letter')
                                ->icon('heroicon-o-document')
                                ->label('Generate')
                                ->visible(fn (?Candidate $record) => $record->getFirstMedia('completion_letter') == null)
                                ->action(function (?Candidate $record) {
                                    dispatch_sync(new GenerateCompletionLetterJob($record));
                                    Notification::make('generated')
                                        ->title('Generating')
                                        ->body('It will be generated in background. Please wait and refresh the page.')
                                        ->success()
                                        ->send();
                                }),
                        ])
                        ->label('Completion Letter')
                        ->collection('completion_letter')
                        ->hiddenOn('create')
                        ->multiple(),

                    SpatieMediaLibraryFileUpload::make('completion_cert')
                        ->hintActions([
                            Action::make('generate_completion_cert')
                                ->icon('heroicon-o-document')
                                ->label('Generate')
                                ->visible(fn (?Candidate $record) => $record->getFirstMedia('completion_cert') == null)
                                ->action(function (?Candidate $record) {
                                    dispatch_sync(new GenerateCompletionCertJob($record));
                                    Notification::make('generated')
                                        ->title('Generating')
                                        ->body('It will be generated in background. Please wait and refresh the page.')
                                        ->success()
                                        ->send();
                                }),
                        ])
                        ->label('Completion Certificate')
                        ->collection('completion_cert')
                        ->hiddenOn('create')
                        ->multiple(),

                    SpatieMediaLibraryFileUpload::make('attendance_report')
                        ->label('Attendance Report')
                        ->collection('attendance_report')
                        ->hiddenOn('create')
                        ->multiple()
                        ->hintActions([
                            Action::make('generate_attendance_report')
                                ->icon('heroicon-o-document')
                                ->label('Generate')
                                ->visible(fn (?Candidate $record) => $record->status === CandidateStatus::COMPLETED && $record->getFirstMedia('attendance_report') == null)
                                ->action(function (?Candidate $record) {
                                    dispatch_sync(new GenerateAttendanceReportJob($record));
                                    Notification::make('generated')
                                        ->title('Generating')
                                        ->body('It will be generated in background. Please wait and refresh the page.')
                                        ->success()
                                        ->send();
                                }),
                        ]),
                ])->heading('Interns Documents')->collapsed(true)->visible(fn (?Candidate $record) => $record?->position?->type == PositionType::INTERN),
            ])->heading('Attachments')->collapsible(),

            Section::make([
                Repeater::make('working_experiences')
                    ->columnSpanFull()
                    ->columns(2)
                    ->defaultItems(0)
                    ->schema([
                        TextInput::make('position')
                            ->prefix('as ')
                            ->inlineLabel()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, Set $set) {
                                if ($state) {
                                    $set('position', str($state)->title());
                                }
                            })
                            ->required(),

                        TextInput::make('company')
                            ->prefix('at ')
                            ->inlineLabel()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, Set $set) {
                                if ($state) {
                                    $set('company', str($state)->trim()->title());
                                }
                            })
                            ->suffixActions([
                                Action::make('google_company')
                                    ->icon('heroicon-o-magnifying-glass')
                                    ->url(fn ($state) => 'https://www.google.com/search?q='.urlencode($state), true),
                            ])
                            ->required(),

                        Select::make('employment_type')
                            ->inlineLabel()
                            ->prefix('working ')
                            ->options([
                                'Full_time' => 'Full time',
                                'Part_time' => 'Part time',
                                'Contract' => 'Contract',
                                'Internship' => 'Internship',
                                'Freelance' => 'Freelance',
                                'Other' => 'Other',
                            ]),

                        TextInput::make('location')
                            ->inlineLabel()
                            ->placeholder('City, Country')
                            ->prefix('in ')

                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, Set $set) {
                                if ($state) {
                                    $set('location', str($state)->trim()->title());
                                }
                            }),

                        RichEditor::make('responsibilities')
                            ->columnSpanFull()
                            ->placeholder('Describe your key responsibilities and achievements'),

                        Fieldset::make('duration')
                            ->inlineLabel()
                            ->label('Employment Period')
                            ->columns(2)
                            ->schema([
                                DatePicker::make('start_date')
                                    ->prefix('from '),
                                DatePicker::make('end_date')
                                    ->prefix('to'),

                                Placeholder::make('range')
                                    ->label('From - To')
                                    ->visibleOn(['view', 'edit'])
                                    ->content(fn (Get $get): string => $get('start_date') !== null
                                        ? Carbon::parse($get('start_date'))->diffForHumans(Carbon::parse($get('end_date') ?? now()), [
                                            'syntax' => CarbonInterface::DIFF_ABSOLUTE,
                                            'parts' => 2,
                                            'join' => ' and ',
                                            'short' => false,
                                        ])
                                        : 'N/A'
                                    ),
                            ]),

                        Toggle::make('is_current')
                            ->inlineLabel()
                            ->label('currently work here')
                            ->live()
                            ->afterStateUpdated(function (bool $state, Set $set) {
                                if ($state) {
                                    $set('end_date', null);
                                }
                            }),
                    ]),
            ])->heading('Working Experiences')->collapsible(),

            Section::make([
                Builder::make('additional_info')
                    ->label('Additional Information')
                    ->blocks([
                        Block::make('source')
                            ->icon('heroicon-o-link')
                            ->schema([
                                Select::make('source')
                                    ->inlineLabel()
                                    ->options([
                                        'LinkedIn' => 'LinkedIn',
                                        'Indeed' => 'Indeed',
                                        'Referral' => 'Referral',
                                        'Email' => 'Email',
                                        'Others' => 'Others',
                                    ])
                                    ->required(),

                                TextInput::make('other_source')
                                    ->inlineLabel()
                                    ->visible(fn (Get $get) => $get('source') === 'Others'),
                            ])
                            ->columns(2),

                        Block::make('qualification')
                            ->icon('heroicon-o-academic-cap')
                            ->schema([
                                Select::make('qualification')
                                    ->inlineLabel()
                                    ->options([
                                        'Diploma' => 'Diploma',
                                        'Bachelor' => 'Bachelor',
                                        'Master' => 'Master',
                                        'PhD' => 'PhD',
                                        'Others' => 'Others',
                                    ]),
                                TextInput::make('major')
                                    ->inlineLabel()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (?string $state, Set $set) {
                                        if ($state) {
                                            $set('major', str($state)->title());
                                        }
                                    })
                                    ->prefix('in '),
                                TextInput::make('university')
                                    ->inlineLabel()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (?string $state, Set $set) {
                                        if ($state) {
                                            $set('university', str($state)->trim()->title());
                                        }
                                    })
                                    ->prefix('from '),
                                TextInput::make('gpa')
                                    ->inlineLabel()
                                    ->prefix('with GPA '),
                                Fieldset::make('from_to')
                                    ->inlineLabel()
                                    ->label('From - To')
                                    ->columns(2)
                                    ->schema([
                                        DatePicker::make('from'),
                                        DatePicker::make('to'),
                                    ]),
                            ])
                            ->columns(2),

                        Block::make('social_media')
                            ->icon('heroicon-o-globe-alt')
                            ->schema([
                                Select::make('social_media')
                                    ->inlineLabel()
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
                                    ->inlineLabel()
                                    ->schema([
                                        TextInput::make('url')
                                            ->inlineLabel()
                                            ->reactive()
                                            ->afterStateUpdated(function (?string $state, Set $set) {
                                                if (! $state) {
                                                    return null;
                                                }

                                                // Convert URL to lowercase for consistent matching
                                                $url = str($state)->lower();

                                                // Match social media platform and extract username
                                                $platform = match (true) {
                                                    $url->contains('linkedin.com') => 'linkedin',
                                                    $url->contains('github.com') => 'github',
                                                    $url->contains('twitter.com') => 'twitter',
                                                    $url->contains('facebook.com') => 'facebook',
                                                    $url->contains('instagram.com') => 'instagram',
                                                    default => 'others'
                                                };

                                                $username = match ($platform) {
                                                    'linkedin' => $url->after('linkedin.com/in/')->before('/')->toString(),
                                                    'github' => $url->after('github.com/')->before('/')->toString(),
                                                    'twitter' => $url->after('twitter.com/')->before('/')->toString(),
                                                    'facebook' => $url->after('facebook.com/')->before('/')->toString(),
                                                    'instagram' => $url->after('instagram.com/')->before('/')->toString(),
                                                    default => null
                                                };

                                                $set('social_media', $platform);
                                                if ($username) {
                                                    $set('username', $username);
                                                }

                                            })
                                            ->suffixAction(
                                                Action::make('view')
                                                    ->icon('heroicon-o-eye')
                                                    ->url(fn (?string $state) => $state, true)
                                            ),

                                        TextInput::make('username')
                                            ->inlineLabel()
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
            ])->heading('Additional Information')->collapsible(),

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

                SpatieTagsColumn::make('skill_tags')
                    ->type('skills')
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
                    ->relationship('position', 'title', function ($query) {
                        $query->whereHas('candidates')
                            ->where('status', PositionStatus::OPEN);
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
            ->recordActions([
                Action::make('status')
                    ->color(Color::Blue)
                    ->icon('heroicon-s-check-circle')
                    ->schema([
                        Select::make('status')
                            ->options(CandidateStatus::class),
                    ])
                    ->action(fn (Candidate $record, array $data) => $record->update($data)),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
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
                        ->visible(fn (Page $livewire) => $livewire instanceof ListCandidates)
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
                    ->visible(fn ($livewire) => $livewire instanceof ViewPositionCandidate)
                    ->icon('heroicon-o-envelope-open')
                    ->form(fn (Collection $records) => [
                        Select::make('email')
                            ->live()
                            ->required()
                            ->options(function ($livewire) {
                                /** @var Position $record */
                                $record = $livewire->record;

                                return Email::where('position_id', $record->id)
                                    ->orderBy('sort')
                                    ->pluck('name', 'id');
                            })
                            ->suffixAction(fn (Get $get) => $get('email') !== null ? Action::make('view_email')
                                ->icon('heroicon-o-eye')
                                ->url(fn () => EmailResource::getUrl('edit', ['record' => $get('email')]), true)
                                : null
                            ),

                        Section::make('Attachments')
                            ->schema([
                                Select::make('attachments')
                                    ->multiple()
                                    ->columnSpanFull()
                                    ->reactive()
                                    ->options(function (Get $get) use ($records) {
                                        $availableAttachments = [];

                                        $position = $records->first()->position;
                                        $emailId = $get('email');
                                        $email = Email::find($emailId);

                                        // Position documents
                                        if ($position?->hasMedia('documents')) {
                                            foreach ($position->getMedia('documents') as $document) {
                                                /** @var Media $document */
                                                $availableAttachments["position_{$document->id}"] = "[Position] {$document->name}";
                                            }
                                        }

                                        // Email template documents
                                        if ($email?->hasMedia('documents')) {
                                            foreach ($email->getMedia('documents') as $document) {
                                                /** @var Media $document */
                                                $availableAttachments["email_{$document->id}"] = "[Email] {$document->name}";
                                            }
                                        }

                                        return $availableAttachments;
                                    }),
                            ]),
                    ])
                    ->action(function (Collection $records, array $data) {
                        $email = Email::find($data['email']);
                        $attachments = null;

                        // Only process attachments if all candidates are from same position
                        if (! empty($data['attachments'])) {
                            $attachments = collect($data['attachments'])
                                ->map(function (string $attachment) use ($records, $email) {
                                    [$type, $id] = explode('_', $attachment, 2);

                                    return match ($type) {
                                        'position' => $records->first()->position->getMedia('documents')->firstWhere('id', $id),
                                        'email' => $email->getMedia('documents')->firstWhere('id', $id),
                                        default => null,
                                    };
                                })
                                ->filter();
                        }

                        $records->each(function (Candidate $record, $index) use ($email, $attachments) {
                            activity()
                                ->performedOn($record)
                                ->causedBy(auth()->user())
                                ->event('send_email')
                                ->log('Email requested to be sent to '.$record->name.' ('.$record->email.')');

                            $mail = new DefaultMail(
                                candidate: $record,
                                email: $email,
                                medias: $attachments,
                            );

                            Mail::to($record->email)
                                ->later(now()->addSeconds($index * 30), $mail);
                        });

                        Notification::make()
                            ->success()
                            ->title('Email Sent')
                            ->body('Email has been sent to '.$records->count().' candidate(s). <br>'.
                                'ETA: <b>'.now()->addSeconds($records->count() * 30)->shortRelativeDiffForHumans().'</b>'
                            )
                            ->send();
                    }),
                BulkAction::make('generate_offer_letter')
                    ->icon('heroicon-o-document')
                    ->deselectRecordsAfterCompletion()
                    ->form(ViewCandidate::getOfferLetterForm())
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
            'index' => ListCandidates::route('/'),
            'create' => CreateCandidate::route('/create'),
            'view' => ViewCandidate::route('/{record}/'),
            'edit' => EditCandidate::route('/{record}/edit'),
            'audit' => AuditCandidate::route('/{record}/audit'),
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

    public static function extractAndFillResumeInformation(string $pdfPath, Set $set, Get $get): array
    {
        $extractor = app(PdfExtractorService::class)->extractInformation($pdfPath);
        $extractedInfo = [];

        if (isset($extractor['personal_info'])) {
            $personalInfo = $extractor['personal_info'];

            if (! empty($personalInfo['name']) && str($get('name'))->isEmpty()) {
                $set('name', str($personalInfo['name'])->title());
                $extractedInfo[] = 'name';
            }

            if (! empty($personalInfo['email']) && str($get('email'))->isEmpty()) {
                $set('email', str($personalInfo['email'])->remove(' ')->remove('`'));
                $extractedInfo[] = 'email';
            }

            if (! empty($personalInfo['phone_number']) && str($get('phone_number'))->isEmpty()) {
                $set('phone_number', $personalInfo['phone_number']);
                $extractedInfo[] = 'phone number';
            }
        }

        // Handle skills array
        if (isset($extractor['skills']) && is_array($extractor['skills'])) {
            $existingSkills = collect($get('skills') ?? []);
            $newSkills = $existingSkills
                ->merge($extractor['skills'])
                ->unique()
                ->values()
                ->toArray();

            if (! empty($newSkills)) {
                $set('skills', $newSkills);
                $extractedInfo[] = 'skills';
            }
        }

        $additionalInfo = [];

        if (isset($extractor['qualifications']) && is_array($extractor['qualifications'])) {
            foreach ($extractor['qualifications'] as $qualificationEntry) {
                if (! isset($qualificationEntry['data'])) {
                    continue;
                }

                $qualification = $qualificationEntry['data'];
                $additionalInfo[] = [
                    'type' => 'qualification',
                    'data' => [
                        'qualification' => $qualification['qualification'] ?? null,
                        'major' => str($qualification['major'] ?? '')->title(),
                        'university' => str($qualification['university'] ?? '')->trim(),
                        'gpa' => $qualification['gpa'] ?? null,
                        'from' => $qualification['from'] ?? null,
                        'to' => $qualification['to'] ?? null,
                    ],
                ];
            }
            if (! empty($extractor['qualifications'])) {
                $extractedInfo[] = 'qualifications';
            }
        }

        if (isset($extractor['social_media']) && is_array($extractor['social_media'])) {
            foreach ($extractor['social_media'] as $socialMediaEntry) {
                if (! isset($socialMediaEntry['data'])) {
                    continue;
                }

                $socialMedia = $socialMediaEntry['data'];
                $additionalInfo[] = [
                    'type' => 'social_media',
                    'data' => [
                        'social_media' => $socialMedia['social_media'] ?? null,
                        'username' => $socialMedia['username'] ?? null,
                        'url' => $socialMedia['url'] ?? null,
                    ],
                ];
            }
            if (! empty($extractor['social_media'])) {
                $extractedInfo[] = 'social media';
            }
        }

        // Handle working experiences for the repeater
        if (isset($extractor['work_experience']) && is_array($extractor['work_experience'])) {
            $workExperiences = [];
            foreach ($extractor['work_experience'] as $experience) {
                $workExperiences[] = [
                    'company' => str($experience['company'] ?? '')->trim(),
                    'position' => str($experience['position'] ?? '')->trim(),
                    'employment_type' => $experience['employment_type'] ?? 'Other',
                    'location' => str($experience['location'] ?? '')->trim()->title(),
                    'start_date' => $experience['start_date'] ?? null,
                    'end_date' => $experience['end_date'] ?? null,
                    'is_current' => $experience['is_current'] ?? false,
                    'responsibilities' => str($experience['responsibilities'] ?? '')->trim(),
                ];
            }

            if (! empty($workExperiences)) {
                $set('working_experiences', $workExperiences);
                $extractedInfo[] = 'work experience';
            }
        }

        if (! empty($additionalInfo)) {
            $set('additional_info', $additionalInfo);
        }

        return $extractedInfo;
    }

    public static function getEloquentQuery(): EloquentBuilder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
