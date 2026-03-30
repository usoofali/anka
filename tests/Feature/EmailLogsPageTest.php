<?php

use App\Models\User;
use Livewire\Livewire;

test('authenticated user can view email logs page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('email-logs.index'))
        ->assertOk();
});

test('email logs component supports status filter property', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::email-logs.index')
        ->set('statusFilter', 'sent')
        ->assertSet('statusFilter', 'sent')
        ->assertHasNoErrors();
});
