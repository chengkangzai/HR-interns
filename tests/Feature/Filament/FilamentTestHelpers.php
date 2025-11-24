<?php

namespace Tests\Feature\Filament;

use App\Models\Candidate;
use App\Models\Email;
use App\Models\Position;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

trait FilamentTestHelpers
{
    /**
     * Setup fake storage for file upload testing
     */
    protected function setupFakeStorage(): void
    {
        Storage::fake('public');
        Storage::fake('s3');
    }

    /**
     * Setup fake queue for job testing
     */
    protected function setupFakeQueue(): void
    {
        Queue::fake();
    }

    /**
     * Create a test uploaded file
     */
    protected function createTestFile(string $name = 'test.pdf', string $mimeType = 'application/pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 100, $mimeType);
    }

    /**
     * Create a test position with defaults
     */
    protected function createTestPosition(array $overrides = []): Position
    {
        return Position::factory()->create(array_merge([
            'title' => 'Software Developer Intern',
            'description' => 'A great internship opportunity',
        ], $overrides));
    }

    /**
     * Create a test candidate with defaults
     */
    protected function createTestCandidate(array $overrides = [], ?Position $position = null): Candidate
    {
        if (!$position) {
            $position = $this->createTestPosition();
        }

        return Candidate::factory()->create(array_merge([
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'phone_number' => '+1234567890',
            'position_id' => $position->id,
        ], $overrides));
    }

    /**
     * Create a test email template
     */
    protected function createTestEmail(array $overrides = [], ?Position $position = null): Email
    {
        if (!$position) {
            $position = $this->createTestPosition();
        }

        return Email::factory()->create(array_merge([
            'subject' => 'Test Email Subject',
            'body' => 'Test email body content',
            'position_id' => $position->id,
        ], $overrides));
    }

    /**
     * Create multiple test candidates
     */
    protected function createTestCandidates(int $count = 3, array $overrides = [], ?Position $position = null): \Illuminate\Database\Eloquent\Collection
    {
        if (!$position) {
            $position = $this->createTestPosition();
        }

        return Candidate::factory()->count($count)->create(array_merge([
            'position_id' => $position->id,
        ], $overrides));
    }

    /**
     * Assert that a job was dispatched
     */
    protected function assertJobDispatched(string $jobClass): void
    {
        Queue::assertPushed($jobClass);
    }

    /**
     * Assert that a notification was sent
     */
    protected function assertNotificationSent(string $message): void
    {
        $this->assertSee($message);
    }
}