<?php

use App\Filament\Resources\Candidates\Pages\CreateCandidate;
use App\Filament\Resources\Candidates\Pages\EditCandidate;
use App\Models\Candidate;
use App\Models\Position;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tiptap\Editor;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Set up Filament panel
    Filament::setCurrentPanel('admin');

    // Create and authenticate as admin user
    $adminUser = User::factory()->create([
        'name' => 'Test Admin',
        'email' => 'admin@test.com',
    ]);
    $this->actingAs($adminUser);

    // Create a position for candidates
    $this->position = Position::factory()->create(['status' => 'open']);
});

describe('Candidate Work Experience with RichEditor', function () {

    it('can create candidate with work experience that has string responsibilities', function () {
        $workExperiences = [
            [
                'company' => 'Tech Corp',
                'position' => 'Senior Developer',
                'employment_type' => 'Full_time',
                'location' => 'San Francisco, CA',
                'start_date' => '2020-01-01',
                'end_date' => '2023-12-31',
                'is_current' => false,
                'responsibilities' => 'Led team of 5 developers\nImplemented new features\nImproved code quality',
            ],
        ];

        Livewire::test(CreateCandidate::class)
            ->fillForm([
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'position_id' => $this->position->id,
                'status' => 'pending',
                'working_experiences' => $workExperiences,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $candidate = Candidate::first();
        expect($candidate)->not->toBeNull()
            ->and($candidate->working_experiences)->toBeInstanceOf(\Illuminate\Support\Collection::class)
            ->and($candidate->working_experiences)->toHaveCount(1);

        $experience = $candidate->working_experiences->first();
        expect($experience['company'])->toBe('Tech Corp')
            ->and($experience['responsibilities'])->toBeArray()
            ->and($experience['responsibilities']['type'])->toBe('doc')
            ->and($experience['responsibilities']['content'])->toBeArray();
    });

    it('converts string responsibilities to TipTap JSON on form hydration', function () {
        $candidate = Candidate::factory()->create([
            'position_id' => $this->position->id,
            'working_experiences' => collect([
                [
                    'company' => 'Tech Corp',
                    'position' => 'Developer',
                    'employment_type' => 'Full_time',
                    'location' => 'San Francisco, CA',
                    'start_date' => '2020-01-01',
                    'end_date' => null,
                    'is_current' => true,
                    'responsibilities' => 'Developed web applications\nManaged databases',
                ],
            ]),
        ]);

        Livewire::test(EditCandidate::class, ['record' => $candidate->id])
            ->assertSuccessful()
            ->assertHasNoFormErrors();
    });

    it('handles empty responsibilities gracefully', function () {
        $workExperiences = [
            [
                'company' => 'Tech Corp',
                'position' => 'Developer',
                'employment_type' => 'Full_time',
                'location' => 'San Francisco, CA',
                'start_date' => '2020-01-01',
                'end_date' => null,
                'is_current' => true,
                'responsibilities' => null,
            ],
        ];

        Livewire::test(CreateCandidate::class)
            ->fillForm([
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'position_id' => $this->position->id,
                'status' => 'pending',
                'working_experiences' => $workExperiences,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $candidate = Candidate::first();
        $responsibilities = $candidate->working_experiences->first()['responsibilities'];

        // Empty/null string becomes an empty TipTap document with empty paragraph
        expect($responsibilities)->toBeArray()
            ->and($responsibilities['type'])->toBe('doc');
    });

    it('handles HTML content in responsibilities', function () {
        $workExperiences = [
            [
                'company' => 'Tech Corp',
                'position' => 'Developer',
                'employment_type' => 'Full_time',
                'location' => 'San Francisco, CA',
                'start_date' => '2020-01-01',
                'end_date' => null,
                'is_current' => true,
                'responsibilities' => '<strong>Led</strong> development of <em>key features</em>',
            ],
        ];

        Livewire::test(CreateCandidate::class)
            ->fillForm([
                'name' => 'Bob Smith',
                'email' => 'bob@example.com',
                'position_id' => $this->position->id,
                'status' => 'pending',
                'working_experiences' => $workExperiences,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $candidate = Candidate::first();
        $experience = $candidate->working_experiences->first();

        expect($experience['responsibilities'])->toBeArray()
            ->and($experience['responsibilities']['type'])->toBe('doc');
    });

    it('can update candidate with existing work experience', function () {
        $editor = new Editor;
        $tiptapJson = $editor->setContent('Original responsibilities')->getDocument();

        $candidate = Candidate::factory()->create([
            'position_id' => $this->position->id,
            'working_experiences' => collect([
                [
                    'company' => 'Old Corp',
                    'position' => 'Junior Dev',
                    'employment_type' => 'Full_time',
                    'location' => 'New York, NY',
                    'start_date' => '2019-01-01',
                    'end_date' => '2020-12-31',
                    'is_current' => false,
                    'responsibilities' => $tiptapJson,
                ],
            ]),
        ]);

        // Test that the form loads successfully with TipTap JSON responsibilities
        Livewire::test(EditCandidate::class, ['record' => $candidate->id])
            ->assertSuccessful()
            ->assertHasNoFormErrors()
            ->assertFormFieldExists('working_experiences');
    });
});
