<?php

use App\Filament\Resources\Candidates\Pages\ListCandidates;
use App\Models\Candidate;
use App\Models\Position;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

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
});

describe('CandidateResource Basic UI Tests', function () {

    it('can render candidate list page', function () {
        Livewire::test(ListCandidates::class)
            ->assertSuccessful();
    });

    it('can render candidate list with data', function () {
        $position = Position::factory()->create(['status' => 'open']);
        Candidate::factory()->count(3)->create(['position_id' => $position->id]);

        Livewire::test(ListCandidates::class)
            ->assertSuccessful();
    });

});