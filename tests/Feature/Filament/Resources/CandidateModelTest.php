<?php

use App\Enums\CandidateStatus;
use App\Models\Candidate;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('s3'); // Also fake S3 for media library
    config(['media-library.disk_name' => 'public']); // Use public disk for testing
    $this->user = User::factory()->create();
});

describe('Candidate Model Tests', function () {

    it('can create a candidate with required fields', function () {
        $position = Position::factory()->create([
            'status' => 'open',
            'type' => 'intern'
        ]);

        $candidate = Candidate::factory()->create([
            'name' => 'Test Candidate',
            'email' => 'test@example.com',
            'phone_number' => '+1234567890',
            'status' => CandidateStatus::PENDING,
            'position_id' => $position->id,
        ]);

        expect($candidate)
            ->name->toBe('Test Candidate')
            ->email->toBe('test@example.com')
            ->status->toBe(CandidateStatus::PENDING)
            ->position_id->toBe($position->id);

        expect($candidate->position)->toBeInstanceOf(Position::class);
    });

    it('can update candidate status', function () {
        $position = Position::factory()->create(['status' => 'open']);
        $candidate = Candidate::factory()->create([
            'status' => CandidateStatus::PENDING,
            'position_id' => $position->id,
        ]);

        $candidate->update(['status' => CandidateStatus::CONTACTED]);

        expect($candidate->fresh()->status)->toBe(CandidateStatus::CONTACTED);
    });

    it('can attach tags to candidate', function () {
        $position = Position::factory()->create(['status' => 'open']);
        $candidate = Candidate::factory()->create(['position_id' => $position->id]);

        $candidate->attachTags(['PHP', 'Laravel', 'JavaScript']);

        expect($candidate->tags()->count())->toBe(3);
        expect($candidate->tags->pluck('name')->toArray())
            ->toContain('PHP')
            ->toContain('Laravel')
            ->toContain('JavaScript');
    });

    it('can attach skills to candidate', function () {
        $position = Position::factory()->create(['status' => 'open']);
        $candidate = Candidate::factory()->create(['position_id' => $position->id]);

        $candidate->attachTags(['Python', 'Django'], 'skills');

        expect($candidate->tagsWithType('skills')->count())->toBe(2);
    });

    it('can soft delete candidate', function () {
        $position = Position::factory()->create(['status' => 'open']);
        $candidate = Candidate::factory()->create(['position_id' => $position->id]);

        $candidate->delete();

        expect($candidate->fresh()->trashed())->toBeTrue();
        expect(Candidate::withoutTrashed()->find($candidate->id))->toBeNull();
        expect(Candidate::withTrashed()->find($candidate->id))->not->toBeNull();
    });

    it('has working position relationship', function () {
        $position = Position::factory()->create([
            'title' => 'Software Engineer Intern',
            'status' => 'open'
        ]);
        $candidate = Candidate::factory()->create(['position_id' => $position->id]);

        expect($candidate->position)->toBeInstanceOf(Position::class);
        expect($candidate->position->title)->toBe('Software Engineer Intern');
        expect($position->candidates()->count())->toBe(1);
    });

    it('can store additional_info as collection', function () {
        $position = Position::factory()->create(['status' => 'open']);
        $additionalInfo = [
            ['type' => 'education', 'content' => 'Bachelor of Computer Science'],
            ['type' => 'certification', 'content' => 'AWS Certified Developer'],
        ];

        $candidate = Candidate::factory()->create([
            'position_id' => $position->id,
            'additional_info' => $additionalInfo,
        ]);

        expect($candidate->additional_info)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($candidate->additional_info->count())->toBe(2);
        expect($candidate->additional_info->first()['type'])->toBe('education');
    });

    it('can store working_experiences as collection', function () {
        $position = Position::factory()->create(['status' => 'open']);
        $workingExperiences = [
            [
                'company' => 'Tech Corp',
                'position' => 'Junior Developer',
                'duration' => '6 months',
                'description' => 'Worked on web applications'
            ],
            [
                'company' => 'StartupXYZ',
                'position' => 'Intern',
                'duration' => '3 months',
                'description' => 'Mobile app development'
            ]
        ];

        $candidate = Candidate::factory()->create([
            'position_id' => $position->id,
            'working_experiences' => $workingExperiences,
        ]);

        expect($candidate->working_experiences)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($candidate->working_experiences->count())->toBe(2);
        expect($candidate->working_experiences->first()['company'])->toBe('Tech Corp');
    });

    it('has activity logging enabled', function () {
        $position = Position::factory()->create(['status' => 'open']);
        $candidate = Candidate::factory()->create(['position_id' => $position->id]);

        $candidate->update(['name' => 'Updated Name']);

        expect($candidate->activities()->count())->toBeGreaterThan(0);
    });

});

describe('Candidate Status Workflow Tests', function () {

    it('can transition through typical hiring workflow', function () {
        $position = Position::factory()->create(['status' => 'open']);
        $candidate = Candidate::factory()->create([
            'status' => CandidateStatus::PENDING,
            'position_id' => $position->id,
        ]);

        // Typical hiring workflow
        $workflow = [
            CandidateStatus::CONTACTED,
            CandidateStatus::TECHNICAL_TEST,
            CandidateStatus::INTERVIEW,
            CandidateStatus::OFFER_ACCEPTED,
            CandidateStatus::HIRED,
            CandidateStatus::COMPLETED,
        ];

        foreach ($workflow as $status) {
            $candidate->update(['status' => $status]);
            expect($candidate->fresh()->status)->toBe($status);
        }
    });

    it('can handle withdrawn status at any point', function () {
        $position = Position::factory()->create(['status' => 'open']);
        $candidate = Candidate::factory()->create([
            'status' => CandidateStatus::INTERVIEW,
            'position_id' => $position->id,
        ]);

        $candidate->update(['status' => CandidateStatus::WITHDRAWN]);
        expect($candidate->fresh()->status)->toBe(CandidateStatus::WITHDRAWN);
    });

});

describe('Candidate Media Collection Tests', function () {

    it('has correct media collections defined', function () {
        $position = Position::factory()->create(['status' => 'open']);
        $candidate = Candidate::factory()->create(['position_id' => $position->id]);

        $expectedCollections = [
            'resumes',
            'other_documents',
            'offer_letters',
            'wfh_letter',
            'completion_letter',
            'attendance_report',
            'completion_cert'
        ];

        foreach ($expectedCollections as $collection) {
            expect($candidate->getMediaCollection($collection))->not->toBeNull();
        }
    });

    it('can add resume file', function () {
        $position = Position::factory()->create(['status' => 'open']);
        $candidate = Candidate::factory()->create(['position_id' => $position->id]);

        $file = UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf');

        $candidate->addMediaFromString($file->getContent())
            ->usingName('Test Resume')
            ->toMediaCollection('resumes');

        expect($candidate->getFirstMedia('resumes'))->not->toBeNull();
        expect($candidate->getFirstMedia('resumes')->name)->toBe('Test Resume');
    });

});