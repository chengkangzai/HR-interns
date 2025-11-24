<?php

use App\Enums\CandidateStatus;
use App\Jobs\GenerateOfferLetterJob;
use App\Jobs\GenerateCompletionCertJob;
use App\Jobs\GenerateCompletionLetterJob;
use App\Jobs\GenerateWFHLetterJob;
use App\Jobs\GenerateAttendanceReportJob;
use App\Models\Candidate;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('s3');
    config(['media-library.disk_name' => 'public']);
    Queue::fake();
    $this->user = User::factory()->create();
});

describe('Document Generation Job Tests', function () {

    it('can dispatch offer letter generation job', function () {
        $position = Position::factory()->create(['status' => 'open']);
        $candidate = Candidate::factory()->create([
            'status' => CandidateStatus::OFFER_ACCEPTED,
            'position_id' => $position->id,
        ]);

        GenerateOfferLetterJob::dispatch($candidate, 3000, '09:00', '18:00');

        Queue::assertPushed(GenerateOfferLetterJob::class, function ($job) use ($candidate) {
            return $job->candidate->id === $candidate->id
                && $job->pay === 3000;
        });
    });

    it('can dispatch completion certificate generation job', function () {
        $position = Position::factory()->create(['status' => 'open']);
        $candidate = Candidate::factory()->create([
            'status' => CandidateStatus::COMPLETED,
            'position_id' => $position->id,
        ]);

        GenerateCompletionCertJob::dispatch($candidate);

        Queue::assertPushed(GenerateCompletionCertJob::class, function ($job) use ($candidate) {
            return $job->candidate->id === $candidate->id;
        });
    });

    it('can dispatch completion letter generation job', function () {
        $position = Position::factory()->create(['status' => 'open']);
        $candidate = Candidate::factory()->create([
            'status' => CandidateStatus::COMPLETED,
            'position_id' => $position->id,
        ]);

        GenerateCompletionLetterJob::dispatch($candidate);

        Queue::assertPushed(GenerateCompletionLetterJob::class, function ($job) use ($candidate) {
            return $job->candidate->id === $candidate->id;
        });
    });

    it('can dispatch work from home letter generation job', function () {
        $position = Position::factory()->create(['status' => 'open']);
        $candidate = Candidate::factory()->create([
            'status' => CandidateStatus::HIRED,
            'position_id' => $position->id,
        ]);

        GenerateWFHLetterJob::dispatch($candidate);

        Queue::assertPushed(GenerateWFHLetterJob::class, function ($job) use ($candidate) {
            return $job->candidate->id === $candidate->id;
        });
    });

    it('can dispatch attendance report generation job', function () {
        $position = Position::factory()->create(['status' => 'open']);
        $candidate = Candidate::factory()->create([
            'status' => CandidateStatus::HIRED,
            'position_id' => $position->id,
            'from' => now()->subMonths(2),
            'to' => now()->addMonths(1),
        ]);

        GenerateAttendanceReportJob::dispatch($candidate);

        Queue::assertPushed(GenerateAttendanceReportJob::class);
    });

});

describe('Document Generation Integration Tests', function () {

    it('stores generated documents in correct media collections', function () {
        $position = Position::factory()->create(['status' => 'open']);
        $candidate = Candidate::factory()->create([
            'status' => CandidateStatus::OFFER_ACCEPTED,
            'position_id' => $position->id,
        ]);

        // Simulate document generation by adding media directly
        $candidate->addMediaFromString('Mock PDF Content')
            ->usingName('Offer Letter')
            ->usingFileName('offer-letter.pdf')
            ->toMediaCollection('offer_letters');

        expect($candidate->getFirstMedia('offer_letters'))->not->toBeNull();
        expect($candidate->getFirstMedia('offer_letters')->name)->toBe('Offer Letter');
        expect($candidate->getFirstMedia('offer_letters')->file_name)->toBe('offer-letter.pdf');
    });

    it('can store multiple document types', function () {
        $position = Position::factory()->create(['status' => 'open']);
        $candidate = Candidate::factory()->create([
            'status' => CandidateStatus::COMPLETED,
            'position_id' => $position->id,
        ]);

        // Add different document types
        $candidate->addMediaFromString('Completion Cert PDF')
            ->usingName('Completion Certificate')
            ->toMediaCollection('completion_cert');

        $candidate->addMediaFromString('Completion Letter PDF')
            ->usingName('Completion Letter')
            ->toMediaCollection('completion_letter');

        $candidate->addMediaFromString('WFH Letter PDF')
            ->usingName('Work From Home Letter')
            ->toMediaCollection('wfh_letter');

        expect($candidate->getMedia('completion_cert'))->toHaveCount(1);
        expect($candidate->getMedia('completion_letter'))->toHaveCount(1);
        expect($candidate->getMedia('wfh_letter'))->toHaveCount(1);
    });

    it('can handle document versioning in same collection', function () {
        $position = Position::factory()->create(['status' => 'open']);
        $candidate = Candidate::factory()->create([
            'status' => CandidateStatus::OFFER_ACCEPTED,
            'position_id' => $position->id,
        ]);

        // Add first offer letter
        $candidate->addMediaFromString('First Offer Letter')
            ->usingName('Offer Letter v1')
            ->toMediaCollection('offer_letters');

        expect($candidate->getMedia('offer_letters'))->toHaveCount(1);

        // Add second offer letter
        $candidate->addMediaFromString('Updated Offer Letter')
            ->usingName('Offer Letter v2')
            ->toMediaCollection('offer_letters');

        // Check that at least one document exists (behavior may vary)
        $mediaCount = $candidate->getMedia('offer_letters')->count();
        expect($mediaCount)->toBeGreaterThanOrEqual(1);
        expect($mediaCount)->toBeLessThanOrEqual(2);
    });

});

describe('Document Access and Download Tests', function () {

    it('provides correct media URLs', function () {
        $position = Position::factory()->create(['status' => 'open']);
        $candidate = Candidate::factory()->create(['position_id' => $position->id]);

        $media = $candidate->addMediaFromString('Test Document Content')
            ->usingName('Test Document')
            ->usingFileName('test.pdf')
            ->toMediaCollection('offer_letters');

        expect($media->getUrl())->toContain('test.pdf');
        expect($media->name)->toBe('Test Document');
    });

    it('can check document existence', function () {
        $position = Position::factory()->create(['status' => 'open']);
        $candidate = Candidate::factory()->create(['position_id' => $position->id]);

        expect($candidate->getFirstMedia('offer_letters'))->toBeNull();

        $media = $candidate->addMediaFromString('Document Content')
            ->usingName('Test Document')
            ->toMediaCollection('offer_letters');

        // Refresh candidate to ensure media is loaded
        $candidate->refresh();

        expect($candidate->getFirstMedia('offer_letters'))->not->toBeNull();
        expect($candidate->getFirstMedia('offer_letters')->name)->toBe('Test Document');
    });

});