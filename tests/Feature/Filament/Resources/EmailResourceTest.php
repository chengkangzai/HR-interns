<?php

use App\Filament\Resources\Emails\Pages\ListEmails;
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

describe('Email Model Tests', function () {

    it('can create an email with required fields', function () {
        $position = Position::factory()->create(['status' => 'open']);
        $email = Email::factory()->create([
            'name' => 'Welcome Email',
            'title' => 'Welcome to Our Team',
            'body' => 'Dear candidate, welcome to our team...',
            'position_id' => $position->id,
        ]);

        expect($email)
            ->name->toBe('Welcome Email')
            ->title->toBe('Welcome to Our Team')
            ->body->toBe('Dear candidate, welcome to our team...')
            ->position_id->toBe($position->id);
    });

    it('can store CC recipients as array', function () {
        $position = Position::factory()->create(['status' => 'open']);
        $ccRecipients = [
            'hr@company.com',
            'manager@company.com',
            'team-lead@company.com',
        ];

        $email = Email::factory()->create([
            'position_id' => $position->id,
            'cc' => $ccRecipients,
        ]);

        expect($email->cc)->toBeArray();
        expect($email->cc)->toHaveCount(3);
        expect($email->cc)->toContain('hr@company.com');
    });

    it('can soft delete email', function () {
        $position = Position::factory()->create(['status' => 'open']);
        $email = Email::factory()->create(['position_id' => $position->id]);

        $email->delete();

        expect($email->fresh()->trashed())->toBeTrue();
        expect(Email::withoutTrashed()->find($email->id))->toBeNull();
        expect(Email::withTrashed()->find($email->id))->not->toBeNull();
    });

    it('has activity logging enabled', function () {
        $position = Position::factory()->create(['status' => 'open']);
        $email = Email::factory()->create(['position_id' => $position->id]);

        $email->update(['title' => 'Updated Email Title']);

        expect($email->activities()->count())->toBeGreaterThan(0);
    });

    it('can store sort order', function () {
        $position = Position::factory()->create(['status' => 'open']);

        $email1 = Email::factory()->create([
            'position_id' => $position->id,
            'sort' => 1,
        ]);

        $email2 = Email::factory()->create([
            'position_id' => $position->id,
            'sort' => 2,
        ]);

        expect($email1->sort)->toBe(1);
        expect($email2->sort)->toBe(2);
    });

});

describe('Email Relationships Tests', function () {

    it('has working position relationship', function () {
        $position = Position::factory()->create([
            'status' => 'open',
            'title' => 'Software Engineer',
        ]);
        $email = Email::factory()->create(['position_id' => $position->id]);

        expect($email->position)->toBeInstanceOf(Position::class);
        expect($email->position->title)->toBe('Software Engineer');
        expect($position->emails()->count())->toBe(1);
    });

    it('can access only open position emails', function () {
        $openPosition = Position::factory()->create(['status' => 'open']);
        $closedPosition = Position::factory()->create(['status' => 'closed']);

        $openEmail = Email::factory()->create(['position_id' => $openPosition->id]);
        $closedEmail = Email::factory()->create(['position_id' => $closedPosition->id]);

        expect($openEmail->openPosition)->not->toBeNull();
        expect($closedEmail->openPosition)->toBeNull();
    });

    it('maintains relationship integrity on position changes', function () {
        $position1 = Position::factory()->create(['status' => 'open']);
        $position2 = Position::factory()->create(['status' => 'open']);

        $email = Email::factory()->create(['position_id' => $position1->id]);

        expect($email->position->id)->toBe($position1->id);

        $email->update(['position_id' => $position2->id]);

        expect($email->fresh()->position->id)->toBe($position2->id);
    });

});

describe('Email Media Collection Tests', function () {

    it('has correct media collections defined', function () {
        $position = Position::factory()->create(['status' => 'open']);
        $email = Email::factory()->create(['position_id' => $position->id]);

        expect($email->getMediaCollection('documents'))->not->toBeNull();
    });

    it('can add document attachments', function () {
        $position = Position::factory()->create(['status' => 'open']);
        $email = Email::factory()->create(['position_id' => $position->id]);

        $file = UploadedFile::fake()->create('attachment.pdf', 100, 'application/pdf');

        $email->addMediaFromString($file->getContent())
            ->usingName('Email Attachment')
            ->usingFileName('attachment.pdf')
            ->toMediaCollection('documents');

        expect($email->getFirstMedia('documents'))->not->toBeNull();
        expect($email->getFirstMedia('documents')->name)->toBe('Email Attachment');
    });

    it('can store multiple attachments', function () {
        $position = Position::factory()->create(['status' => 'open']);
        $email = Email::factory()->create(['position_id' => $position->id]);

        $email->addMediaFromString('First Attachment Content')
            ->usingName('Attachment 1')
            ->toMediaCollection('documents');

        $email->addMediaFromString('Second Attachment Content')
            ->usingName('Attachment 2')
            ->toMediaCollection('documents');

        expect($email->getMedia('documents'))->toHaveCount(2);
    });

    it('provides correct attachment URLs', function () {
        $position = Position::factory()->create(['status' => 'open']);
        $email = Email::factory()->create(['position_id' => $position->id]);

        $media = $email->addMediaFromString('Test Attachment')
            ->usingName('Test Document')
            ->usingFileName('test.pdf')
            ->toMediaCollection('documents');

        expect($media->getUrl())->toContain('test.pdf');
        expect($media->name)->toBe('Test Document');
    });

});

describe('Email Resource Filament Tests', function () {

    it('can render email list page', function () {
        $position = Position::factory()->create(['status' => 'open']);
        Email::factory()->count(3)->create(['position_id' => $position->id]);

        Livewire::test(ListEmails::class)
            ->assertSuccessful();
    });

    it('can display emails from different positions', function () {
        $position1 = Position::factory()->create(['status' => 'open', 'title' => 'Backend Dev']);
        $position2 = Position::factory()->create(['status' => 'open', 'title' => 'Frontend Dev']);

        Email::factory()->create([
            'position_id' => $position1->id,
            'name' => 'Backend Welcome',
        ]);

        Email::factory()->create([
            'position_id' => $position2->id,
            'name' => 'Frontend Welcome',
        ]);

        Livewire::test(ListEmails::class)
            ->assertSuccessful();
    });

});

describe('Email Business Logic Tests', function () {

    it('can organize emails by position', function () {
        $position1 = Position::factory()->create(['status' => 'open']);
        $position2 = Position::factory()->create(['status' => 'open']);

        Email::factory()->count(2)->create(['position_id' => $position1->id]);
        Email::factory()->count(3)->create(['position_id' => $position2->id]);

        $position1Emails = Email::where('position_id', $position1->id)->count();
        $position2Emails = Email::where('position_id', $position2->id)->count();

        expect($position1Emails)->toBe(2);
        expect($position2Emails)->toBe(3);
    });

    it('can sort emails by sort order', function () {
        $position = Position::factory()->create(['status' => 'open']);

        $email1 = Email::factory()->create([
            'position_id' => $position->id,
            'sort' => 3,
            'name' => 'Third Email',
        ]);

        $email2 = Email::factory()->create([
            'position_id' => $position->id,
            'sort' => 1,
            'name' => 'First Email',
        ]);

        $email3 = Email::factory()->create([
            'position_id' => $position->id,
            'sort' => 2,
            'name' => 'Second Email',
        ]);

        $sortedEmails = Email::where('position_id', $position->id)
            ->orderBy('sort', 'asc')
            ->get();

        expect($sortedEmails)->toHaveCount(3);

        // Check that the sorting order is correct by examining sort values
        $sortValues = $sortedEmails->pluck('sort')->toArray();
        expect($sortValues)->toEqual([1, 2, 3]);

        // Verify the first and last items are correct
        expect($sortedEmails->first()->sort)->toBe(1);
        expect($sortedEmails->last()->sort)->toBe(3);
    });

    it('validates email content structure', function () {
        $position = Position::factory()->create(['status' => 'open']);

        $email = Email::factory()->create([
            'position_id' => $position->id,
            'name' => 'Test Email',
            'title' => 'Subject Line',
            'body' => '<p>Rich HTML content</p>',
        ]);

        expect($email->name)->toBeString();
        expect($email->title)->toBeString();
        expect($email->body)->toBeString();
        expect(strlen($email->body))->toBeGreaterThan(0);
    });

    it('handles CC recipients validation', function () {
        $position = Position::factory()->create(['status' => 'open']);

        // Valid CC array
        $validCC = ['user1@example.com', 'user2@example.com'];
        $email1 = Email::factory()->create([
            'position_id' => $position->id,
            'cc' => $validCC,
        ]);

        expect($email1->cc)->toBe($validCC);

        // Empty CC array
        $email2 = Email::factory()->create([
            'position_id' => $position->id,
            'cc' => [],
        ]);

        expect($email2->cc)->toBe([]);

        // Null CC (should work)
        $email3 = Email::factory()->create([
            'position_id' => $position->id,
            'cc' => null,
        ]);

        expect($email3->cc)->toBeNull();
    });

});
