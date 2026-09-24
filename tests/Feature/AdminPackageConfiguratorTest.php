<?php

use App\Models\CustomPackageInquiry;
use App\Models\Destination;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can access package configurator page', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->get(route('admin.packages.configurator'));

    $response->assertStatus(200);
    $response->assertSee('Ready-Made Package');
    $response->assertSee('Customize Package');
    $response->assertSee('Quotations &amp; Inquiries', false);
});

test('admin can configure and save a ready made package', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $destination = Destination::create([
        'name' => 'Boracay Island',
        'location' => 'Aklan, Philippines',
        'description' => 'Top white beach',
        'image' => 'images/destinations/boracay.jpg',
        'starting_price' => '₱5,000',
        'type' => 'domestic',
        'is_featured' => true,
    ]);

    $response = $this->actingAs($admin)->post(route('admin.packages.configurator.ready-made'), [
        'title' => 'Boracay Beach Luxury Getaway',
        'destination_id' => $destination->id,
        'category' => 'domestic',
        'duration' => '4 Days / 3 Nights',
        'price_amount' => 24500.00,
        'price_currency' => 'PHP',
        'number_of_pax' => 2,
        'hotel_name' => 'Henann Regency Resort & Spa',
        'has_breakfast' => '1',
        'bed_config' => 'queen',
        'smoking_preference' => 'non_smoking',
        'pet_friendly' => '0',
        'has_transportation' => '1',
        'transportation_type' => 'Airport Speedboat & Van Transfer',
        'check_in_date' => now()->addDays(10)->toDateString(),
        'check_out_date' => now()->addDays(14)->toDateString(),
        'special_requests' => 'Sea-view room with welcome drinks',
        'description' => 'Complete Boracay vacation with hotel and speedboat transfers.',
        'status' => 'active',
        'is_featured' => '1',
    ]);

    $response->assertRedirect(route('admin.packages.configurator', ['tab' => 'ready-made']));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('travel_packages', [
        'title' => 'Boracay Beach Luxury Getaway',
        'package_type' => 'ready_made',
        'hotel_name' => 'Henann Regency Resort & Spa',
        'has_breakfast' => 1,
        'bed_config' => 'queen',
        'smoking_preference' => 'non_smoking',
        'has_transportation' => 1,
        'transportation_type' => 'Airport Speedboat & Van Transfer',
        'status' => 'active',
    ]);
});

test('agent can submit and configure a custom package inquiry', function () {
    $agent = User::factory()->create([
        'role' => 'agent',
        'allowed_pages' => ['packages'],
    ]);

    $response = $this->actingAs($agent)->post(route('admin.packages.configurator.custom'), [
        'client_name' => 'Maria Santos',
        'client_email' => 'maria.santos@example.com',
        'client_phone' => '09171234567',
        'destination_name' => 'Tokyo, Japan',
        'travel_type' => 'international',
        'duration' => '6 Days / 5 Nights',
        'number_of_pax' => 4,
        'adults_count' => 3,
        'children_count' => 1,
        'infants_count' => 0,
        'hotel_name' => 'Shinjuku Granbell Hotel',
        'preferred_hotel' => 'Near JR Yamanote line',
        'has_breakfast' => '1',
        'bed_config' => 'twin',
        'smoking_preference' => 'non_smoking',
        'pet_friendly' => '0',
        'has_transportation' => '1',
        'transportation_type' => 'Private Airport Van + Tokyo Subway Passes',
        'check_in_date' => now()->addMonth()->toDateString(),
        'check_out_date' => now()->addMonth()->addDays(6)->toDateString(),
        'estimated_budget' => 150000.00,
        'special_requests' => 'Request connecting rooms and high floor.',
    ]);

    $this->assertDatabaseHas('custom_package_inquiries', [
        'client_name' => 'Maria Santos',
        'client_email' => 'maria.santos@example.com',
        'destination_name' => 'Tokyo, Japan',
        'number_of_pax' => 4,
        'hotel_name' => 'Shinjuku Granbell Hotel',
        'has_breakfast' => 1,
        'bed_config' => 'twin',
        'has_transportation' => 1,
        'status' => 'pending',
    ]);

    $inquiry = CustomPackageInquiry::where('client_email', 'maria.santos@example.com')->first();
    expect($inquiry->reference_number)->toStartWith('CP-');

    $response->assertRedirect(route('admin.packages.custom-inquiries.show', $inquiry));
    $response->assertSessionHas('success');
});

test('admin can view and update custom package inquiry status', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $inquiry = CustomPackageInquiry::create([
        'reference_number' => 'CP-20260923-0001',
        'client_name' => 'John Doe',
        'client_email' => 'john@example.com',
        'client_phone' => '09180001122',
        'destination_name' => 'Batanes, Philippines',
        'duration' => '4 Days / 3 Nights',
        'travel_type' => 'domestic',
        'number_of_pax' => 2,
        'adults_count' => 2,
        'children_count' => 0,
        'infants_count' => 0,
        'hotel_name' => 'Fundacion Pacita',
        'has_breakfast' => true,
        'bed_config' => 'king',
        'smoking_preference' => 'non_smoking',
        'pet_friendly' => false,
        'has_transportation' => true,
        'transportation_type' => 'Private 4x4 Van',
        'check_in_date' => now()->addDays(20)->toDateString(),
        'check_out_date' => now()->addDays(24)->toDateString(),
        'estimated_budget' => 65000.00,
        'status' => 'pending',
    ]);

    $viewResponse = $this->actingAs($admin)->get(route('admin.packages.custom-inquiries.show', $inquiry));
    $viewResponse->assertStatus(200);
    $viewResponse->assertSee('Batanes, Philippines');
    $viewResponse->assertSee('Fundacion Pacita');
    $viewResponse->assertSee('CP-20260923-0001');

    $statusResponse = $this->actingAs($admin)->patch(route('admin.packages.custom-inquiries.status', $inquiry), [
        'status' => 'quoted',
        'agent_notes' => 'Quotation generated and sent to client email.',
    ]);

    $statusResponse->assertRedirect();
    $this->assertDatabaseHas('custom_package_inquiries', [
        'id' => $inquiry->id,
        'status' => 'quoted',
        'agent_notes' => 'Quotation generated and sent to client email.',
    ]);
});

test('admin can delete a custom package inquiry', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $inquiry = CustomPackageInquiry::create([
        'reference_number' => 'CP-20260923-0099',
        'client_name' => 'Cancelled Client',
        'client_email' => 'cancel@example.com',
        'destination_name' => 'Discarded Destination',
        'travel_type' => 'domestic',
        'number_of_pax' => 1,
        'adults_count' => 1,
        'children_count' => 0,
        'infants_count' => 0,
        'status' => 'cancelled',
    ]);

    $response = $this->actingAs($admin)->delete(route('admin.packages.custom-inquiries.destroy', $inquiry));

    $response->assertRedirect(route('admin.packages.configurator', ['tab' => 'inquiries']));
    $this->assertDatabaseMissing('custom_package_inquiries', [
        'id' => $inquiry->id,
    ]);
});
