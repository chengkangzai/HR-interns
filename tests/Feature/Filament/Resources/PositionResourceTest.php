<?php

use App\Enums\PositionStatus;
use App\Enums\PositionType;
use App\Filament\Resources\Positions\Pages\ListPositions;
use App\Models\Candidate;
use App\Models\Email;
use App\Models\Position;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('s3');
    config(['media-library.disk_name' => 'public']);

    // Set up Filament panel
    Filament::setCurrentPanel('admin');

    // Create and authenticate as admin user
    $adminUser = User::factory()->create([
        'name' => 'Test Admin',
        'email' => 'admin@test.com',
    ]);
    $this->actingAs($adminUser);
});

describe('Position Model Tests', function () {

    it('can create a position with required fields', function () {
        $position = Position::factory()->create([
            'title' => 'Software Engineer Intern',
            'description' => 'Great internship opportunity',
            'status' => PositionStatus::OPEN,
            'type' => PositionType::INTERN,
        ]);

        expect($position)
            ->title->toBe('Software Engineer Intern')
            ->description->toBe('Great internship opportunity')
            ->status->toBe(PositionStatus::OPEN)
            ->type->toBe(PositionType::INTERN);
    });

    it('can store URLs as array', function () {
        $urls = [
            'https://jobs.example.com/position/123',
            'https://linkedin.com/jobs/456',
            'https://indeed.com/viewjob?jk=789',
        ];

        $position = Position::factory()->create([
            'urls' => $urls,
        ]);

        expect($position->urls)->toBeArray();
        expect($position->urls)->toHaveCount(3);
        expect($position->urls)->toContain('https://jobs.example.com/position/123');
    });

    it('has correct enum values for status', function () {
        $openPosition = Position::factory()->create(['status' => PositionStatus::OPEN]);
        $closedPosition = Position::factory()->create(['status' => PositionStatus::CLOSED]);

        expect($openPosition->status)->toBe(PositionStatus::OPEN);
        expect($closedPosition->status)->toBe(PositionStatus::CLOSED);
        expect($openPosition->status->getLabel())->toBe('Open');
        expect($closedPosition->status->getLabel())->toBe('Closed');
    });

    it('has correct enum values for type', function () {
        $internPosition = Position::factory()->create(['type' => PositionType::INTERN]);
        $fullTimePosition = Position::factory()->create(['type' => PositionType::FULL_TIME]);

        expect($internPosition->type)->toBe(PositionType::INTERN);
        expect($fullTimePosition->type)->toBe(PositionType::FULL_TIME);
        expect($internPosition->type->getLabel())->toBe('Internship');
        expect($fullTimePosition->type->getLabel())->toBe('Full-Time Position');
    });

    it('can soft delete position', function () {
        $position = Position::factory()->create();

        $position->delete();

        expect($position->fresh()->trashed())->toBeTrue();
        expect(Position::withoutTrashed()->find($position->id))->toBeNull();
        expect(Position::withTrashed()->find($position->id))->not->toBeNull();
    });

    it('has activity logging enabled', function () {
        $position = Position::factory()->create();

        $position->update(['title' => 'Updated Position Title']);

        expect($position->activities()->count())->toBeGreaterThan(0);
    });

});

describe('Position Relationships Tests', function () {

    it('has working candidates relationship', function () {
        $position = Position::factory()->create(['status' => PositionStatus::OPEN]);
        $candidates = Candidate::factory()->count(3)->create(['position_id' => $position->id]);

        expect($position->candidates()->count())->toBe(3);
        expect($position->candidates->first())->toBeInstanceOf(Candidate::class);

        $candidate = $candidates->first();
        expect($candidate->position)->toBeInstanceOf(Position::class);
        expect($candidate->position->id)->toBe($position->id);
    });

    it('has working emails relationship', function () {
        $position = Position::factory()->create(['status' => PositionStatus::OPEN]);
        $emails = Email::factory()->count(2)->create(['position_id' => $position->id]);

        expect($position->emails()->count())->toBe(2);
        expect($position->emails->first())->toBeInstanceOf(Email::class);

        $email = $emails->first();
        expect($email->position)->toBeInstanceOf(Position::class);
        expect($email->position->id)->toBe($position->id);
    });

    it('cascades relationships on delete', function () {
        $position = Position::factory()->create(['status' => PositionStatus::OPEN]);
        $candidate = Candidate::factory()->create(['position_id' => $position->id]);
        $email = Email::factory()->create(['position_id' => $position->id]);

        $position->delete();

        // Check that related records still exist but position is soft deleted
        expect($candidate->fresh())->not->toBeNull();
        expect($email->fresh())->not->toBeNull();
        expect($position->fresh()->trashed())->toBeTrue();
    });

});

describe('Position Media Collection Tests', function () {

    it('has correct media collections defined', function () {
        $position = Position::factory()->create();

        expect($position->getMediaCollection('documents'))->not->toBeNull();
    });

    it('can add document files', function () {
        $position = Position::factory()->create();

        $file = UploadedFile::fake()->create('job-description.pdf', 100, 'application/pdf');

        $position->addMediaFromString($file->getContent())
            ->usingName('Job Description')
            ->usingFileName('job-description.pdf')
            ->toMediaCollection('documents');

        expect($position->getFirstMedia('documents'))->not->toBeNull();
        expect($position->getFirstMedia('documents')->name)->toBe('Job Description');
    });

    it('can store multiple documents', function () {
        $position = Position::factory()->create();

        $position->addMediaFromString('Job Description Content')
            ->usingName('Job Description')
            ->toMediaCollection('documents');

        $position->addMediaFromString('Requirements Document')
            ->usingName('Requirements')
            ->toMediaCollection('documents');

        expect($position->getMedia('documents'))->toHaveCount(2);
    });

});

describe('Position Resource Filament Tests', function () {

    it('can render position list page', function () {
        Position::factory()->count(3)->create([
            'status' => PositionStatus::OPEN,
        ]);

        Livewire::test(ListPositions::class)
            ->assertSuccessful();
    });

    it('can display positions with different statuses', function () {
        Position::factory()->create(['status' => PositionStatus::OPEN, 'title' => 'Open Position']);
        Position::factory()->create(['status' => PositionStatus::CLOSED, 'title' => 'Closed Position']);

        Livewire::test(ListPositions::class)
            ->assertSuccessful();
    });

    it('can display positions with different types', function () {
        Position::factory()->create(['type' => PositionType::INTERN, 'title' => 'Internship Position']);
        Position::factory()->create(['type' => PositionType::FULL_TIME, 'title' => 'Full Time Position']);

        Livewire::test(ListPositions::class)
            ->assertSuccessful();
    });

});

describe('Position Business Logic Tests', function () {

    it('can track open positions for candidate assignments', function () {
        $openPositions = Position::factory()->count(3)->create(['status' => PositionStatus::OPEN]);
        $closedPositions = Position::factory()->count(2)->create(['status' => PositionStatus::CLOSED]);

        $openCount = Position::where('status', PositionStatus::OPEN)->count();
        $totalCount = Position::count();

        expect($openCount)->toBe(3);
        expect($totalCount)->toBe(5);
    });

    it('can categorize positions by type', function () {
        Position::factory()->count(2)->create(['type' => PositionType::INTERN]);
        Position::factory()->count(3)->create(['type' => PositionType::FULL_TIME]);
        Position::factory()->count(1)->create(['type' => PositionType::CONTRACT]);

        $internships = Position::where('type', PositionType::INTERN)->count();
        $fullTime = Position::where('type', PositionType::FULL_TIME)->count();
        $contracts = Position::where('type', PositionType::CONTRACT)->count();

        expect($internships)->toBe(2);
        expect($fullTime)->toBe(3);
        expect($contracts)->toBe(1);
    });

    it('validates position status transitions', function () {
        $position = Position::factory()->create(['status' => PositionStatus::OPEN]);

        // Can close an open position
        $position->update(['status' => PositionStatus::CLOSED]);
        expect($position->fresh()->status)->toBe(PositionStatus::CLOSED);

        // Can reopen a closed position
        $position->update(['status' => PositionStatus::OPEN]);
        expect($position->fresh()->status)->toBe(PositionStatus::OPEN);
    });

});
