<?php

use App\Filament\Resources\Candidates\Pages\CreateCandidate;
use App\Models\Position;
use App\Models\User;
use App\Services\PdfExtractorService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\StructuredResponseFake;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->create());
});

/**
 * Real-world payload returned by OpenAI strict structured output: empty strings
 * instead of null and partial dates such as a bare year.
 *
 * @return array<string, mixed>
 */
function openAiResumePayload(): array
{
    return [
        'personal_info' => [
            'name' => 'SHARMILAH GANESAN',
            'email' => 'sharmilah@example.com',
            'phone_number' => '+60127127517',
        ],
        'skills' => ['Customer Service', 'Salesforce'],
        'social_media' => [],
        'qualifications' => [
            [
                'type' => 'qualification',
                'data' => [
                    'university' => 'Open University Malaysia (OUM)',
                    'qualification' => 'Bachelor',
                    'major' => 'human resource management',
                    'gpa' => '',
                    'from' => '',
                    'to' => '2026',
                ],
            ],
        ],
        'work_experience' => [
            [
                'company' => 'Lumitics',
                'position' => 'Client Success Operations Specialist',
                'employment_type' => 'Full_time',
                'location' => '',
                'start_date' => '2024-07-01',
                'end_date' => '',
                'is_current' => true,
                'responsibilities' => 'Monitored and responded to client inquiries.',
            ],
            [
                'company' => 'StoreHub Malaysia',
                'position' => 'Merchant Care Executive',
                'employment_type' => 'Full_time',
                'location' => 'Kuala Lumpur',
                'start_date' => '2022-05',
                'end_date' => '2023-10-01',
                'is_current' => false,
                'responsibilities' => 'Communicated product benefits to merchants.',
            ],
        ],
    ];
}

function fakeResumePdf(): UploadedFile
{
    $pdf = Pdf::loadHTML('<h1>Sharmilah Ganesan</h1><p>Client Success Operations Specialist</p>')->output();

    return test()->resumePdf = UploadedFile::fake()->createWithContent('resume.pdf', $pdf);
}

it('auto fills the create form from an uploaded resume', function () {
    Prism::fake([
        StructuredResponseFake::make()->withStructured(openAiResumePayload()),
    ]);

    $component = Livewire::test(CreateCandidate::class)
        ->set('data.resume', [fakeResumePdf()]);

    $component
        ->assertHasNoErrors()
        ->assertSchemaStateSet([
            'name' => 'Sharmilah Ganesan',
            'email' => 'sharmilah@example.com',
            'phone_number' => '+60127127517',
        ]);

    $state = $component->instance()->form->getRawState();

    expect($state['skills'])->toContain('Customer Service', 'Salesforce');

    $experiences = array_values($state['working_experiences']);
    expect($experiences)->toHaveCount(2)
        ->and($experiences[0]['company'])->toBe('Lumitics')
        ->and($experiences[0]['end_date'])->toBeNull()
        ->and($experiences[0]['location'])->toBeNull()
        ->and($experiences[0]['is_current'])->toBeTrue()
        ->and($experiences[0]['responsibilities'])->toBeArray()
        ->and($experiences[1]['start_date'])->toBe('2022-05-01');

    $qualification = array_values($state['additional_info'])[0];
    expect($qualification['type'])->toBe('qualification')
        ->and($qualification['data']['major'])->toBe('Human Resource Management')
        ->and($qualification['data']['gpa'])->toBeNull()
        ->and($qualification['data']['from'])->toBeNull()
        ->and($qualification['data']['to'])->toBe('2026-01-01');
});

it('saves the candidate after auto fill without validation errors', function () {
    Prism::fake([
        StructuredResponseFake::make()->withStructured(openAiResumePayload()),
    ]);

    Livewire::test(CreateCandidate::class)
        ->set('data.resume', [fakeResumePdf()])
        ->fillForm(['position_id' => Position::factory()->create(['status' => 'open'])->id])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('candidates', [
        'email' => 'sharmilah@example.com',
    ]);
});

it('does not overwrite fields the user already typed', function () {
    Prism::fake([
        StructuredResponseFake::make()->withStructured(openAiResumePayload()),
    ]);

    Livewire::test(CreateCandidate::class)
        ->fillForm(['name' => 'Typed Name'])
        ->set('data.resume', [fakeResumePdf()])
        ->assertSchemaStateSet(['name' => 'Typed Name']);
});

it('normalises unknown enum values returned by the model', function () {
    $payload = openAiResumePayload();
    $payload['qualifications'][0]['data']['qualification'] = 'Degree';
    $payload['work_experience'][0]['employment_type'] = 'Permanent';
    $payload['social_media'] = [
        ['type' => 'social_media', 'data' => ['social_media' => 'Behance', 'username' => 'sg', 'url' => 'https://behance.net/sg']],
    ];

    Prism::fake([StructuredResponseFake::make()->withStructured($payload)]);

    $result = app(PdfExtractorService::class)
        ->extractInformation(fakeResumePdf()->getRealPath());

    expect($result['qualifications'][0]['data']['qualification'])->toBe('Others')
        ->and($result['work_experience'][0]['employment_type'])->toBe('Other')
        ->and($result['social_media'][0]['data']['social_media'])->toBe('others');
});

it('normalises partial and blank dates', function (string $raw, ?string $expected) {
    $payload = openAiResumePayload();
    $payload['work_experience'][0]['start_date'] = $raw;

    Prism::fake([StructuredResponseFake::make()->withStructured($payload)]);

    $result = app(PdfExtractorService::class)
        ->extractInformation(fakeResumePdf()->getRealPath());

    expect($result['work_experience'][0]['start_date'])->toBe($expected);
})->with([
    'full date' => ['2024-07-15', '2024-07-15'],
    'year only' => ['2026', '2026-01-01'],
    'year and month' => ['2024-7', '2024-07-01'],
    'month name' => ['July 2024', '2024-07-01'],
    'empty string' => ['', null],
    'present' => ['Present', null],
    'garbage' => ['sometime soon', null],
]);

it('returns an empty structure when the resume cannot be read', function () {
    Prism::fake([]);

    $result = app(PdfExtractorService::class)
        ->extractInformation('/path/that/does/not/exist.pdf');

    expect($result)->toBe([
        'personal_info' => [],
        'social_media' => [],
        'qualifications' => [],
        'work_experience' => [],
        'skills' => [],
    ]);
});
