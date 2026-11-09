<?php

use App\Models\Destination;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function currencyDestination(string $price): Destination
{
    return Destination::create([
        'name' => 'Tokyo', 'location' => 'Japan', 'description' => 'City.', 'image' => 'newassets/boracay.jpg',
        'starting_price' => $price, 'type' => 'international', 'is_featured' => true,
    ]);
}

function currencyPayload(array $overrides = []): array
{
    return array_merge(['name' => 'Tokyo', 'location' => 'Japan', 'type' => 'international', 'description' => 'City.'], $overrides);
}

test('the price can be switched between pesos and dollars', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $destination = currencyDestination('₱12,000');

    $this->actingAs($admin)->put(route('admin.destinations.update', $destination), currencyPayload(['price_currency' => 'USD', 'price_amount' => '2499']))
        ->assertSessionHasNoErrors();
    expect($destination->fresh()->starting_price)->toBe('$2,499');

    $this->actingAs($admin)->put(route('admin.destinations.update', $destination), currencyPayload(['price_currency' => 'PHP', 'price_amount' => '45000.50']))
        ->assertSessionHasNoErrors();
    expect($destination->fresh()->starting_price)->toBe('₱45,000.50');
});

test('the edit form shows the saved currency and amount', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $html = $this->actingAs($admin)->get(route('admin.destinations.edit', currencyDestination('$2,499')))->assertOk()->getContent();

    expect($html)->toContain('name="price_currency"')->toContain('value="2499"')->toMatch('/<option value="USD"\s+selected/');
});

test('a bad currency or amount is refused', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $destination = currencyDestination('₱12,000');

    $this->actingAs($admin)->put(route('admin.destinations.update', $destination), currencyPayload(['price_currency' => 'EUR', 'price_amount' => '10']))
        ->assertSessionHasErrors('price_currency');
    $this->actingAs($admin)->put(route('admin.destinations.update', $destination), currencyPayload(['price_currency' => 'PHP', 'price_amount' => 'abc']))
        ->assertSessionHasErrors('price_amount');
    expect($destination->fresh()->starting_price)->toBe('₱12,000');
});
