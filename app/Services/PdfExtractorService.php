<?php

namespace App\Services;

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
    public function extractInformation(string $pdfPath): array
    {
        $pdfText = (new Pdf)
            ->setPdf($pdfPath)
            ->text();

        $skills = Tag::query()
            ->whereType('skills')
            ->pluck('name')
            ->toJson();

        try {
            $schema = $this->buildResumeSchema();

            $response = Prism::structured()
                ->using(Provider::OpenAI, 'gpt-4o')
                ->withSchema($schema)
                ->withPrompt(view('prompts.resume-extraction', [
                    'skills' => $skills,
                    'pdfText' => $pdfText,
                ])->render())
                ->asStructured();

            $result = $response->structured;

            if (isset($result['qualifications'])) {
                foreach ($result['qualifications'] as &$qual) {
                    $qualification = data_get($qual, 'data.qualification');
                    if (! in_array($qualification, ['Diploma', 'Bachelor', 'Master', 'PhD', 'Others'])) {
                        data_set($qual, 'data.qualification', 'Others');
                    }
                }
            }

            if (isset($result['social_media'])) {
                foreach ($result['social_media'] as &$social) {
                    $socialMedia = data_get($social, 'data.social_media');
                    if (! in_array($socialMedia, ['linkedin', 'github', 'twitter', 'facebook', 'instagram', 'others'])) {
                        data_set($social, 'data.social_media', 'others');
                    }
                }
            }

            if (isset($result['work_experience'])) {
                foreach ($result['work_experience'] as &$work) {
                    $employmentType = data_get($work, 'employment_type');
                    if (! in_array($employmentType, ['Full_time', 'Part_time', 'Contract', 'Internship', 'Freelance', 'Other'])) {
                        data_set($work, 'employment_type', 'Other');
                    }
                }
            }

            return $result;

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
                                    new StringSchema('gpa', 'GPA or null if not available'),
                                    new StringSchema('from', 'Start date in YYYY-MM-DD format or null'),
                                    new StringSchema('to', 'End date in YYYY-MM-DD format or null if ongoing'),
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
                            new StringSchema('location', 'City, State/Country format'),
                            new StringSchema('start_date', 'Start date in YYYY-MM-DD format'),
                            new StringSchema('end_date', 'End date in YYYY-MM-DD format or null if current'),
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
