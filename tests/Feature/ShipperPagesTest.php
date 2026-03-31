<?php

declare(strict_types=1);

use App\Models\City;
use App\Models\Country;
use App\Models\Shipper;
use App\Models\Staff;
use App\Models\State;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('shipper index is forbidden without shippers.view', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('shippers.index'))
        ->assertForbidden();
});

test('super admin can open shipper index', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $this->actingAs($admin)
        ->get(route('shippers.index'))
        ->assertOk();
});

test('shipper create route is not registered', function (): void {
    $this->get('/shippers/create')->assertNotFound();
});

test('legacy shipper edit URL is not registered', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $shipper = Shipper::factory()->create();

    $this->actingAs($admin)
        ->get('/shippers/'.$shipper->id.'/edit')
        ->assertNotFound();
});

test('super admin can open shipper edit modal from index', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $shipper = Shipper::factory()->create();

    Livewire::actingAs($admin)
        ->test('pages::shippers.index')
        ->call('openEditModal', $shipper->id)
        ->assertSet('showEditModal', true)
        ->assertSet('shipperEditingId', $shipper->id);
});

test('staff can open shipper edit modal from index', function (): void {
    $staffUser = User::factory()->create();
    $staffUser->assignRole('staff_operator');
    Staff::factory()->create(['user_id' => $staffUser->id]);
    $shipper = Shipper::factory()->create();

    Livewire::actingAs($staffUser)
        ->test('pages::shippers.index')
        ->call('openEditModal', $shipper->id)
        ->assertSet('showEditModal', true)
        ->assertSet('shipperEditingId', $shipper->id);
});

test('shipper cannot open edit modal for own company', function (): void {
    $owner = User::factory()->create();
    $owner->assignRole('shipper');
    $ownShipper = Shipper::factory()->create(['user_id' => $owner->id]);

    Livewire::actingAs($owner)
        ->test('pages::shippers.index')
        ->call('openEditModal', $ownShipper->id)
        ->assertSet('showEditModal', false)
        ->assertSet('shipperEditingId', null);
});

test('shipper cannot open edit modal for another company', function (): void {
    $owner = User::factory()->create();
    $owner->assignRole('shipper');
    Shipper::factory()->create(['user_id' => $owner->id]);

    $other = User::factory()->create();
    $other->assignRole('shipper');
    $otherShipper = Shipper::factory()->create(['user_id' => $other->id]);

    Livewire::actingAs($owner)
        ->test('pages::shippers.index')
        ->call('openEditModal', $otherShipper->id)
        ->assertSet('showEditModal', false)
        ->assertSet('shipperEditingId', null);
});

test('staff can delete shipper from index', function (): void {
    $staffUser = User::factory()->create();
    $staffUser->assignRole('staff_operator');
    Staff::factory()->create(['user_id' => $staffUser->id]);
    $shipper = Shipper::factory()->create();
    $ownerId = $shipper->user_id;

    $this->actingAs($staffUser);

    Livewire::test('pages::shippers.index')
        ->call('openDeleteModal', $shipper->id)
        ->assertSet('showDeleteModal', true)
        ->call('deleteShipper');

    expect(Shipper::query()->whereKey($shipper->id)->exists())->toBeFalse();
    expect(User::query()->whereKey($ownerId)->exists())->toBeFalse();
});

test('shipper cannot open delete confirmation', function (): void {
    $user = User::factory()->create();
    $user->assignRole('shipper');
    $shipper = Shipper::factory()->create(['user_id' => $user->id]);

    expect($user->can('delete', $shipper))->toBeFalse();

    $this->actingAs($user);

    Livewire::test('pages::shippers.index')
        ->call('openDeleteModal', $shipper->id)
        ->assertSet('showDeleteModal', false)
        ->assertSet('shipperPendingDeleteId', null);
});

test('super admin csv import creates user shipper role wallet and consignee', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $country = Country::factory()->create(['iso2' => 'US']);
    $state = State::factory()->create(['country_id' => $country->id, 'code' => 'CA']);
    $city = City::factory()->create(['state_id' => $state->id, 'name' => 'Los Angeles']);

    $csv = "owner_name,owner_email,owner_password,company_name,phone,address,country_iso2,state_code,city_name\n".
        'Jane Doe,jane.import@example.com,Password1!,Acme Import Co,+15551234567,123 Harbor Way,US,CA,'.$city->name."\n";

    $file = UploadedFile::fake()->createWithContent('shippers.csv', $csv);

    Livewire::actingAs($admin)
        ->test('pages::shippers.index')
        ->set('importFile', $file)
        ->call('importCsv')
        ->assertHasNoErrors();

    $user = User::query()->where('email', 'jane.import@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->hasRole('shipper'))->toBeTrue();

    $shipper = Shipper::query()->where('user_id', $user->id)->first();

    expect($shipper)->not->toBeNull()
        ->and($shipper->company_name)->toBe('Acme Import Co')
        ->and($shipper->country_id)->toBe($country->id)
        ->and($shipper->state_id)->toBe($state->id)
        ->and($shipper->city_id)->toBe($city->id);

    $this->assertDatabaseHas('wallets', ['shipper_id' => $shipper->id]);
    $this->assertDatabaseHas('consignees', [
        'shipper_id' => $shipper->id,
        'is_default' => true,
    ]);
});

test('super admin csv import updates existing shipper by owner email', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $country = Country::factory()->create(['iso2' => 'US']);
    $state = State::factory()->create(['country_id' => $country->id, 'code' => 'TX']);
    $city = City::factory()->create(['state_id' => $state->id, 'name' => 'Houston']);

    $user = User::factory()->create();
    $user->assignRole('shipper');

    $shipper = Shipper::query()->create([
        'user_id' => $user->id,
        'company_name' => 'Old Company Name',
        'phone' => '+15550001111',
        'address' => '100 Main St',
        'country_id' => $country->id,
        'state_id' => $state->id,
        'city_id' => $city->id,
    ]);

    $csv = "owner_name,owner_email,owner_password,company_name,phone,address,country_iso2,state_code,city_name\n".
        'Updated Name,'.$user->email.',,Updated Company Name,'.$shipper->phone.','.$shipper->address.','.
        $country->iso2.','.$state->code.','.$city->name."\n";

    $file = UploadedFile::fake()->createWithContent('shippers.csv', $csv);

    Livewire::actingAs($admin)
        ->test('pages::shippers.index')
        ->set('importFile', $file)
        ->call('importCsv')
        ->assertHasNoErrors();

    expect($shipper->fresh()->company_name)->toBe('Updated Company Name');
    expect($user->fresh()->name)->toBe('Updated Name');
});

test('super admin csv import skips row when city cannot be resolved', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $country = Country::factory()->create(['iso2' => 'US']);
    $state = State::factory()->create(['country_id' => $country->id, 'code' => 'CA']);
    City::factory()->create(['state_id' => $state->id, 'name' => 'Los Angeles']);

    $csv = "owner_name,owner_email,owner_password,company_name,phone,address,country_iso2,state_code,city_name\n".
        "Ghost User,ghost.city@example.com,Password1!,Co,+15550000000,Addr,US,CA,Nonexistent City\n";

    $file = UploadedFile::fake()->createWithContent('shippers.csv', $csv);

    Livewire::actingAs($admin)
        ->test('pages::shippers.index')
        ->set('importFile', $file)
        ->call('importCsv')
        ->assertHasNoErrors();

    expect(User::query()->where('email', 'ghost.city@example.com')->exists())->toBeFalse();
});

test('shippers sample csv template is downloadable', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $this->actingAs($admin)
        ->get(route('import-templates.geo', 'shippers'))
        ->assertOk();
});
