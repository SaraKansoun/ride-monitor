<?php

use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Role::findOrCreate('driver');
});

test('guests can view the login page', function () {
    $this->get(route('login'))
        ->assertSuccessful()
        ->assertSee('Sign in');
});

test('users can log in with valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'driver@example.com',
        'password' => 'password',
    ]);
    $user->assignRole('driver');

    $this->post(route('login.store'), [
        'email' => 'driver@example.com',
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('users cannot log in with invalid credentials', function () {
    $user = User::factory()->create([
        'email' => 'driver@example.com',
        'password' => 'password',
    ]);
    $user->assignRole('driver');

    $this->post(route('login.store'), [
        'email' => 'driver@example.com',
        'password' => 'wrong-password',
    ])->assertInvalid(['email']);

    $this->assertGuest();
});

test('authenticated users can access the dashboard', function () {
    $user = User::factory()->create();
    $user->assignRole('driver');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('Driver workspace');
});

test('guests are redirected away from the dashboard', function () {
    $this->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

test('users can log out', function () {
    $user = User::factory()->create();
    $user->assignRole('driver');

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});
