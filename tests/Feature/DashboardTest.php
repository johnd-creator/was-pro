<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard')
            ->has('stats')
            ->has('pendingApprovals')
            ->has('wasteByCategory')
            ->has('transportationByStatus')
            ->has('fabaStats')
            ->has('fabaChart', 6)
            ->has('wasteChart', 6)
            ->has('notificationSummary')
            ->has('header')
        );
});
