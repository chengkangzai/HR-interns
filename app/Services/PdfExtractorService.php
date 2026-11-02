<?php

namespace App\Services;

use Carbon\Carbon;
use Exception;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\BooleanSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Spatie\PdfToText\Pdf;
use Spatie\Tags\Tag;

class PdfExtractorService
{
    private const array QUALIFICATIONS = ['Diploma', 'Bachelor', 'Master', 'PhD', 'Others'];

    private const array SOCIAL_MEDIA = ['linkedin', 'github', 'twitter', 'facebook', 'instagram', 'others'];

    private const array EMPLOYMENT_TYPES = ['Full_time', 'Part_time', 'Contract', 'Internship', 'Freelance', 'Other'];

    /**
     * @return array{personal_info: array<string, ?string>, skills: array<int, string>, social_media: array<int, array<string, mixed>>, qualifications: array<int, array<string, mixed>>, work_experience: array<int, array<string, mixed>>}
     */
    public function extractInformation(string $pdfPath): array
    {
        try {
            $pdfText = (new Pdf)
                ->setPdf($pdfPath)
                ->text();

            $skills = Tag::query()
                ->whereType('skills')
                ->pluck('name')
                ->toJson();

            $response = Prism::structured()
                ->using(Provider::OpenAI, 'gpt-4o')
                ->withSchema($this->buildResumeSchema())
                ->withPrompt(view('prompts.resume-extraction', [
                    'skills' => $skills,
                    'pdfText' => $pdfText,
                ])->render())
                ->asStructured();

            return $this->normalize($response->structured ?? []);
        } catch (Exception $e) {
            Log::error('AI extraction failed: '.$e->getMessage());

            Notification::make('AI extraction failed')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return [
                'personal_info' => [],
                'social_media' => [],
                'qualifications' => [],
                'work_experience' => [],
                'skills' => [],
            ];
        }
    }

    /**
     * Clean up the model output: OpenAI strict mode returns "" instead of null
     * and often partial dates such as "2026" or "2024-07".
     *
     * @param  array<string, mixed>  $result
     * @return array{personal_info: array<string, ?string>, skills: array<int, string>, social_media: array<int, array<string, mixed>>, qualifications: array<int, array<string, mixed>>, work_experience: array<int, array<string, mixed>>}
     */
    private function normalize(array $result): array
    {
        $personalInfo = collect($result['personal_info'] ?? [])
            ->map(fn (mixed $value): ?string => $this->blankToNull($value))
            ->all();

        $skills = collect($result['skills'] ?? [])
            ->map(fn (mixed $skill): ?string => $this->blankToNull($skill))
            ->filter()
            ->values()
            ->all();

        $qualifications = collect($result['qualifications'] ?? [])
            ->filter(fn (mixed $entry): bool => is_array(data_get($entry, 'data')))
            ->map(function (array $entry): array {
                $data = $entry['data'];
                $qualification = $this->blankToNull($data['qualification'] ?? null);

                return [
                    'type' => 'qualification',
                    'data' => [
                        'university' => $this->blankToNull($data['university'] ?? null),
                        'qualification' => in_array($qualification, self::QUALIFICATIONS, true) ? $qualification : 'Others',
                        'major' => $this->blankToNull($data['major'] ?? null),
                        'gpa' => $this->blankToNull($data['gpa'] ?? null),
                        'from' => $this->normalizeDate($data['from'] ?? null),
                        'to' => $this->normalizeDate($data['to'] ?? null),
                    ],
                ];
            })
            ->values()
            ->all();

        $socialMedia = collect($result['social_media'] ?? [])
            ->filter(fn (mixed $entry): bool => is_array(data_get($entry, 'data')))
            ->map(function (array $entry): array {
                $data = $entry['data'];
                $platform = str($data['social_media'] ?? '')->lower()->trim()->toString();

                return [
                    'type' => 'social_media',
                    'data' => [
                        'social_media' => in_array($platform, self::SOCIAL_MEDIA, true) ? $platform : 'others',
                        'username' => $this->blankToNull($data['username'] ?? null),
                        'url' => $this->blankToNull($data['url'] ?? null),
                    ],
                ];
            })
            ->values()
            ->all();

        $workExperience = collect($result['work_experience'] ?? [])
            ->filter(fn (mixed $entry): bool => is_array($entry))
            ->map(function (array $work): array {
                $employmentType = $this->blankToNull($work['employment_type'] ?? null);
                $endDate = $this->normalizeDate($work['end_date'] ?? null);

                return [
                    'company' => $this->blankToNull($work['company'] ?? null),
                    'position' => $this->blankToNull($work['position'] ?? null),
                    'employment_type' => in_array($employmentType, self::EMPLOYMENT_TYPES, true) ? $employmentType : 'Other',
                    'location' => $this->blankToNull($work['location'] ?? null),
                    'start_date' => $this->normalizeDate($work['start_date'] ?? null),
                    'end_date' => $endDate,
                    'is_current' => (bool) ($work['is_current'] ?? false),
                    'responsibilities' => $this->blankToNull($work['responsibilities'] ?? null),
                ];
            })
            ->values()
            ->all();

        return [
            'personal_info' => $personalInfo,
            'skills' => $skills,
            'social_media' => $socialMedia,
            'qualifications' => $qualifications,
            'work_experience' => $workExperience,
        ];
    }

    private function blankToNull(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return in_array(strtolower($value), ['', 'null', 'n/a', 'na', '-'], true) ? null : $value;
    }

    private function normalizeDate(mixed $value): ?string
    {
        $value = $this->blankToNull($value);

        if ($value === null || in_array(strtolower($value), ['present', 'current', 'now', 'ongoing'], true)) {
            return null;
        }

        if (preg_match('/^\d{4}$/', $value)) {
            return "{$value}-01-01";
        }

        if (preg_match('/^(\d{4})-(\d{1,2})$/', $value, $matches)) {
            return sprintf('%s-%02d-01', $matches[1], $matches[2]);
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Exception) {
            return null;
        }
    }

    private function buildResumeSchema(): ObjectSchema
    {
        return new ObjectSchema(
            name: 'resume_data',
            description: 'Structured data extracted from resume',
            properties: [
                new ObjectSchema(
                    name: 'personal_info',
                    description: 'Personal information of the candidate',
                    properties: [
                        new StringSchema('name', 'Full name of the candidate'),
                        new StringSchema('email', 'Email address'),
                        new StringSchema('phone_number', 'Phone number with international code'),
                    ],
                    requiredFields: ['name', 'email', 'phone_number']
                ),
                new ArraySchema(
                    name: 'skills',
                    description: 'Array of skill names',
                    items: new StringSchema('skill', 'A skill name')
                ),
                new ArraySchema(
                    name: 'social_media',
                    description: 'Social media profiles',
                    items: new ObjectSchema(
                        name: 'social_media_entry',
                        description: 'A social media profile entry',
                        properties: [
                            new StringSchema('type', 'Must be "social_media"'),
                            new ObjectSchema(
                                name: 'data',
                                description: 'Social media data',
                                properties: [
                                    new StringSchema('social_media', 'Type: linkedin, github, twitter, facebook, instagram, others'),
                                    new StringSchema('username', 'Username on the platform'),
                                    new StringSchema('url', 'Full URL to the profile'),
                                ],
                                requiredFields: ['social_media', 'username', 'url']
                            ),
                        ],
                        requiredFields: ['type', 'data']
                    )
                ),
                new ArraySchema(
                    name: 'qualifications',
                    description: 'Academic qualifications',
                    items: new ObjectSchema(
                        name: 'qualification_entry',
                        description: 'A qualification entry',
                        properties: [
                            new StringSchema('type', 'Must be "qualification"'),
                            new ObjectSchema(
                                name: 'data',
                                description: 'Qualification data',
                                properties: [
                                    new StringSchema('university', 'Full institution name'),
                                    new StringSchema('qualification', 'One of: Diploma, Bachelor, Master, PhD, Others'),
                                    new StringSchema('major', 'Field of study'),
                                    new StringSchema('gpa', 'GPA or null if not available', nullable: true),
                                    new StringSchema('from', 'Start date in YYYY-MM-DD format or null', nullable: true),
                                    new StringSchema('to', 'End date in YYYY-MM-DD format or null if ongoing', nullable: true),
                                ],
                                requiredFields: ['university', 'qualification', 'major', 'gpa', 'from', 'to']
                            ),
                        ],
                        requiredFields: ['type', 'data']
                    )
                ),
                new ArraySchema(
                    name: 'work_experience',
                    description: 'Work experience entries',
                    items: new ObjectSchema(
                        name: 'work_entry',
                        description: 'A work experience entry',
                        properties: [
                            new StringSchema('company', 'Company name'),
                            new StringSchema('position', 'Job title'),
                            new StringSchema('employment_type', 'One of: Full_time, Part_time, Contract, Internship, Freelance, Other'),
                            new StringSchema('location', 'City, State/Country format', nullable: true),
                            new StringSchema('start_date', 'Start date in YYYY-MM-DD format', nullable: true),
                            new StringSchema('end_date', 'End date in YYYY-MM-DD format or null if current', nullable: true),
                            new BooleanSchema('is_current', 'Whether this is a current position'),
                            new StringSchema('responsibilities', 'Job description and responsibilities'),
                        ],
                        requiredFields: ['company', 'position', 'employment_type', 'location', 'start_date', 'end_date', 'is_current', 'responsibilities']
                    )
                ),
            ],
            requiredFields: ['personal_info', 'skills', 'social_media', 'qualifications', 'work_experience']
        );
    }
}
