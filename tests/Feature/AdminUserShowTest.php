<?php

use App\Models\CustomPackageInquiry;
use App\Models\ImmigrationClient;
use App\Models\TicketBooking;
use App\Models\User;
use App\Models\VisaApplication;

test('admin can view user details with ticketing, visa, and immigration history', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $client = User::factory()->create([
        'role' => 'client',
        'name' => 'Maria Elena Santos',
        'email' => 'maria.santos@example.com',
        'phone' => '+63 917 111 2233',
        'passport_number' => 'P1234567A',
    ]);

    // 1. Ticketing record
    TicketBooking::create([
        'booking_reference' => 'TKT-TEST-001',
        'user_id' => $client->id,
        'contact_name' => $client->name,
        'contact_email' => $client->email,
        'contact_phone' => $client->phone,
        'origin' => 'MNL',
        'destination' => 'NRT',
        'trip_type' => 'roundtrip',
        'departure_date' => now()->addDays(20),
        'total_passengers' => 2,
        'total_amount' => 45000,
        'status' => 'confirmed',
        'payment_status' => 'fully_paid',
    ]);

    // 2. Visa record
    VisaApplication::create([
        'reference' => 'VISA-TEST-001',
        'service_type' => 'visit_visa',
        'client_name' => $client->name,
        'client_email' => $client->email,
        'destination_country' => 'Japan',
        'purpose' => 'tourist',
        'processing_speed' => 'regular',
        'status' => 'lodged',
        'total_amount' => 8500,
        'amount_paid' => 8500,
    ]);

    // 3. Immigration record
    $imm = ImmigrationClient::create([
        'user_id' => $client->id,
        'last_name' => 'Santos',
        'given_name' => 'Maria Elena',
        'email' => $client->email,
        'passport_number' => 'P1234567A',
        'nationality' => 'Filipino',
        'visa_expiry_date' => now()->addMonths(6),
        'is_expired' => false,
        'has_penalty' => false,
    ]);

    // 4. Custom Package Inquiry
    CustomPackageInquiry::create([
        'reference_number' => 'CPI-TEST-USER',
        'user_id' => $client->id,
        'client_name' => $client->name,
        'client_email' => $client->email,
        'destination_name' => 'Batanes',
        'number_of_pax' => 2,
        'estimated_budget' => 50000,
        'status' => 'quoted',
    ]);

    $response = $this->actingAs($admin)->get(route('admin.users.show', $client));

    $response->assertSuccessful();
    $response->assertSee('Maria Elena Santos');
    $response->assertSee('TKT-TEST-001');
    $response->assertSee('NRT');
    $response->assertSee('VISA-TEST-001');
    $response->assertSee('Japan');
    $response->assertSee('P1234567A');
    $response->assertSee('CPI-TEST-USER');
    $response->assertSee('Batanes');
});
