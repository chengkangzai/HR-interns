<?php

namespace Tests\Feature\Filament;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

abstract class FilamentTestCase extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up Filament panel
        Filament::setCurrentPanel('admin');

        // Create an admin user for testing
        $this->adminUser = User::factory()->create([
            'name' => 'Test Admin',
            'email' => 'admin@test.com',
        ]);
    }

    /**
     * Act as an authenticated admin user
     */
    protected function actingAsAdmin(): static
    {
        return $this->actingAs($this->adminUser);
    }

    /**
     * Create a new user and act as them
     */
    protected function actingAsNewUser(): static
    {
        $user = User::factory()->create();

        return $this->actingAs($user);
    }

    /**
     * Assert that a Livewire component can see table records
     */
    protected function assertCanSeeTableRecords($component, $records): void
    {
        $component->assertCanSeeTableRecords($records);
    }

    /**
     * Assert that a Livewire component cannot see table records
     */
    protected function assertCanNotSeeTableRecords($component, $records): void
    {
        $component->assertCanNotSeeTableRecords($records);
    }

    /**
     * Fill form fields in Livewire component
     */
    protected function fillFormFields($component, array $data)
    {
        return $component->fillForm($data);
    }
}
