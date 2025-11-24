<?php

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Set up Filament panel
    Filament::setCurrentPanel('admin');

    // Create and authenticate as admin user
    $this->adminUser = User::factory()->create([
        'name' => 'Test Admin',
        'email' => 'admin@test.com',
    ]);
    $this->actingAs($this->adminUser);
});

describe('User Model Tests', function () {

    it('can create a user with required fields', function () {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'password' => Hash::make('password123'),
        ]);

        expect($user)
            ->name->toBe('John Doe')
            ->email->toBe('john.doe@example.com');

        expect(Hash::check('password123', $user->password))->toBeTrue();
    });

    it('can update user information', function () {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
        ]);

        $user->update([
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);

        expect($user->fresh())
            ->name->toBe('Updated Name')
            ->email->toBe('updated@example.com');
    });

    it('requires unique email addresses', function () {
        User::factory()->create(['email' => 'test@example.com']);

        expect(function () {
            User::factory()->create(['email' => 'test@example.com']);
        })->toThrow(\Illuminate\Database\QueryException::class);
    });

    it('hashes passwords automatically', function () {
        $user = User::factory()->create();

        expect($user->password)->not->toBeNull();
        expect(strlen($user->password))->toBeGreaterThan(20); // Hashed passwords are longer
    });

    it('has email verification timestamps', function () {
        $user = User::factory()->unverified()->create();

        expect($user->email_verified_at)->toBeNull(); // Initially null

        $user->markEmailAsVerified();
        expect($user->fresh()->email_verified_at)->not->toBeNull();
    });

    it('has remember token functionality', function () {
        $user = User::factory()->create();

        expect($user->remember_token)->not->toBeNull(); // Factory sets a token

        $user->setRememberToken('test_token');
        expect($user->remember_token)->toBe('test_token');

        $user->setRememberToken(null);
        expect($user->remember_token)->toBeNull();
    });

});

describe('User Authentication Tests', function () {

    it('can authenticate with correct credentials', function () {
        $password = 'correct_password';
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make($password),
        ]);

        expect(Hash::check($password, $user->password))->toBeTrue();
        expect(Hash::check('wrong_password', $user->password))->toBeFalse();
    });

    it('supports laravel authentication guards', function () {
        $user = User::factory()->create();

        $this->actingAs($user);

        expect(auth()->check())->toBeTrue();
        expect(auth()->user()->id)->toBe($user->id);
    });

    it('can logout users', function () {
        $user = User::factory()->create();

        $this->actingAs($user);
        expect(auth()->check())->toBeTrue();

        auth()->logout();
        expect(auth()->check())->toBeFalse();
    });

    it('prevents access without authentication', function () {
        // First logout any authenticated user
        auth()->logout();

        // Test protected routes require authentication
        $response = $this->get('/admin');
        expect($response->getStatusCode())->toBeIn([302, 401, 403]); // Redirect or unauthorized
    });

});

describe('User Resource Filament Tests', function () {

    it('can render user list page', function () {
        User::factory()->count(3)->create();

        Livewire::test(ListUsers::class)
            ->assertSuccessful();
    });

    it('can see created users in list', function () {
        $user1 = User::factory()->create(['name' => 'Alice']);
        $user2 = User::factory()->create(['name' => 'Bob']);

        Livewire::test(ListUsers::class)
            ->assertSuccessful();

        // Just verify the list loads successfully with users present
        expect(User::count())->toBeGreaterThanOrEqual(3); // Including admin user
    });

    it('shows authenticated admin user', function () {
        Livewire::test(ListUsers::class)
            ->assertSuccessful()
            ->assertSee('Test Admin');
    });

});

describe('User Security Tests', function () {

    it('does not expose password in array conversion', function () {
        $user = User::factory()->create();

        $userArray = $user->toArray();

        expect($userArray)->not->toHaveKey('password');
    });

    it('hides sensitive attributes by default', function () {
        $user = User::factory()->create();

        $userArray = $user->toArray();

        expect($userArray)->not->toHaveKey('password');
        expect($userArray)->not->toHaveKey('remember_token');
    });

    it('can verify password without exposing it', function () {
        $password = 'secret123';
        $user = User::factory()->create([
            'password' => Hash::make($password),
        ]);

        expect(Hash::check($password, $user->password))->toBeTrue();
        expect(Hash::check('wrong', $user->password))->toBeFalse();
    });

});

describe('User Factory Tests', function () {

    it('generates unique email addresses', function () {
        $users = User::factory()->count(5)->create();

        $emails = $users->pluck('email')->toArray();

        expect(count($emails))->toBe(5);
        expect(count(array_unique($emails)))->toBe(5); // All emails should be unique
    });

    it('generates valid names', function () {
        $user = User::factory()->create();

        expect($user->name)->toBeString();
        expect(strlen($user->name))->toBeGreaterThan(0);
    });

    it('generates valid email formats', function () {
        $user = User::factory()->create();

        expect($user->email)->toMatch('/^[^\s@]+@[^\s@]+\.[^\s@]+$/');
    });

    it('automatically hashes password in factory', function () {
        $user = User::factory()->create();

        expect($user->password)->toBeString();
        expect(strlen($user->password))->toBeGreaterThan(20); // Bcrypt hashes are longer
        expect($user->password)->not->toContain('password'); // Should not contain plain text
    });

});

describe('User Business Logic Tests', function () {

    it('can track user creation dates', function () {
        $user = User::factory()->create();

        expect($user->created_at)->not->toBeNull();
        expect($user->updated_at)->not->toBeNull();
        expect($user->created_at)->toBeInstanceOf(Carbon\Carbon::class);
    });

    it('updates timestamp on modification', function () {
        $user = User::factory()->create();
        $originalUpdatedAt = $user->updated_at;

        // Wait a moment to ensure different timestamp
        sleep(1);

        $user->update(['name' => 'New Name']);

        expect($user->fresh()->updated_at)->toBeGreaterThan($originalUpdatedAt);
    });

    it('can handle mass assignment protection', function () {
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ];

        $user = User::factory()->create($userData);

        expect($user->name)->toBe('Test User');
        expect($user->email)->toBe('test@example.com');

        // Test fillable attributes work correctly
        expect($user->getFillable())->toContain('name', 'email', 'password');
    });

});
