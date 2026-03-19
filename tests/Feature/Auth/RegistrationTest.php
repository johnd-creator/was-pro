<?php

use App\Models\User;

test('home redirects guests to the login page', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('login'));
});

test('home redirects authenticated users to the dashboard', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertRedirect(route('dashboard', absolute: false));
});

test('registration is not publicly available', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [])->assertNotFound();
});
