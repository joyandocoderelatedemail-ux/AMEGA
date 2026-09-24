<?php

use App\Models\CrmLead;
use App\Models\CrmNote;
use App\Models\CustomPackageInquiry;
use App\Models\Inquiry;
use App\Models\User;

test('admin can access CRM sales pipeline index page', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->get(route('admin.crm.index'));

    $response->assertSuccessful();
    $response->assertSee('Travel Sales Pipeline');
    $response->assertSee('Active Pipeline Value');
    $response->assertSee('New Leads');
    $response->assertSee('Proposal / Quoted');
});

test('agent with inquiries or bookings permission can access CRM', function () {
    $agent = User::factory()->create([
        'role' => 'agent',
        'allowed_pages' => ['dashboard', 'inquiries', 'crm'],
    ]);

    $response = $this->actingAs($agent)->get(route('admin.crm.index'));

    $response->assertSuccessful();
});

test('client user cannot access CRM', function () {
    $client = User::factory()->create(['role' => 'client']);

    $response = $this->actingAs($client)->get(route('admin.crm.index'));

    $response->assertRedirect();
});

test('unauthenticated user is redirected from CRM to login', function () {
    $response = $this->get(route('admin.crm.index'));

    $response->assertRedirect(route('login'));
});

test('can create a new manual CRM lead', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->post(route('admin.crm.leads.store'), [
        'client_name' => 'Alexander Hamilton',
        'client_email' => 'alex@example.com',
        'client_phone' => '+63 918 555 4321',
        'service_type' => 'custom_tour',
        'source' => 'walk_in',
        'title' => '5D4N Batanes Scenic Expedition',
        'destination' => 'Batanes',
        'travel_date' => now()->addMonth()->format('Y-m-d'),
        'number_of_pax' => 2,
        'estimated_value' => 85000,
        'stage' => 'new',
        'priority' => 'high',
        'notes' => 'Client walked in inquiring about flight and homestay inclusions.',
    ]);

    $response->assertRedirect(route('admin.crm.index'));
    $this->assertDatabaseHas('crm_leads', [
        'client_name' => 'Alexander Hamilton',
        'client_email' => 'alex@example.com',
        'destination' => 'Batanes',
        'stage' => 'new',
        'priority' => 'high',
    ]);

    // Initial note was created
    $lead = CrmLead::where('client_email', 'alex@example.com')->first();
    expect($lead)->not->toBeNull();
    expect($lead->notes()->count())->toBe(1);
});

test('can advance CRM lead stage and records history note', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $lead = CrmLead::create([
        'reference_code' => CrmLead::generateReferenceCode(),
        'client_name' => 'Samantha Cruz',
        'client_email' => 'samantha@example.com',
        'service_type' => 'custom_tour',
        'source' => 'phone',
        'title' => 'Boracay Weekend Trip',
        'estimated_value' => 35000,
        'stage' => 'new',
        'priority' => 'medium',
    ]);

    $response = $this->actingAs($admin)->post(route('admin.crm.leads.stage', $lead), [
        'stage' => 'quoted',
    ]);

    $response->assertRedirect();
    $lead->refresh();
    expect($lead->stage)->toBe('quoted');

    // CrmNote of status change was automatically created
    $note = $lead->notes()->latest()->first();
    expect($note)->not->toBeNull();
    expect($note->action_type)->toBe('status_change');
    expect($note->content)->toContain('Quoted');
});

test('winning a lead linked to custom package inquiry updates inquiry status to booked', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $inquiry = CustomPackageInquiry::create([
        'reference_number' => 'CPI-2026-TESTCRM',
        'client_name' => 'Beatriz Perez',
        'client_email' => 'beatriz@example.com',
        'destination_name' => 'El Nido',
        'travel_type' => 'domestic',
        'number_of_pax' => 4,
        'estimated_budget' => 60000,
        'status' => 'pending',
    ]);

    $lead = CrmLead::create([
        'reference_code' => CrmLead::generateReferenceCode(),
        'client_name' => $inquiry->client_name,
        'client_email' => $inquiry->client_email,
        'service_type' => 'custom_tour',
        'source' => 'website',
        'title' => 'Custom Tour: El Nido',
        'destination' => 'El Nido',
        'estimated_value' => 60000,
        'stage' => 'new',
        'priority' => 'high',
        'source_type' => CustomPackageInquiry::class,
        'source_id' => $inquiry->id,
    ]);

    // Move lead to won
    $this->actingAs($admin)->post(route('admin.crm.leads.stage', $lead), [
        'stage' => 'won',
    ]);

    $lead->refresh();
    $inquiry->refresh();

    expect($lead->stage)->toBe('won');
    expect($lead->closed_at)->not->toBeNull();
    expect($inquiry->status)->toBe('booked');
});

test('can log follow-up activity note on a lead', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $lead = CrmLead::create([
        'reference_code' => CrmLead::generateReferenceCode(),
        'client_name' => 'Carlos Ramos',
        'title' => 'Japan Visa Consultation',
        'service_type' => 'visa_assistance',
        'source' => 'phone',
        'stage' => 'contacted',
        'priority' => 'urgent',
    ]);

    $response = $this->actingAs($admin)->post(route('admin.crm.leads.notes', $lead), [
        'action_type' => 'call',
        'content' => 'Called client regarding missing ITR documents. Client will email them tomorrow.',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('crm_notes', [
        'crm_lead_id' => $lead->id,
        'action_type' => 'call',
        'content' => 'Called client regarding missing ITR documents. Client will email them tomorrow.',
    ]);
});

test('sync endpoint imports existing unlinked inquiries and quotations into CRM', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    // Create an unlinked web inquiry
    Inquiry::create([
        'name' => 'John Doe Web',
        'email' => 'johndoe@web.com',
        'phone' => '09123456789',
        'message' => 'Interested in Cebu packages.',
        'service_requested' => 'Tour Packages',
        'status' => 'pending',
    ]);

    // Create an unlinked custom package inquiry
    CustomPackageInquiry::create([
        'reference_number' => 'CPI-SYNC-001',
        'client_name' => 'Jane Smith Custom',
        'client_email' => 'jane@custom.com',
        'destination_name' => 'Siargao',
        'travel_type' => 'domestic',
        'number_of_pax' => 2,
        'estimated_budget' => 45000,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($admin)->post(route('admin.crm.sync'));

    $response->assertRedirect(route('admin.crm.index'));

    $this->assertDatabaseHas('crm_leads', [
        'client_name' => 'John Doe Web',
        'service_type' => 'general',
    ]);

    $this->assertDatabaseHas('crm_leads', [
        'client_name' => 'Jane Smith Custom',
        'service_type' => 'custom_tour',
    ]);
});

test('can delete a CRM lead', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $lead = CrmLead::create([
        'reference_code' => CrmLead::generateReferenceCode(),
        'client_name' => 'Temporary Test Lead',
        'title' => 'Test Lead',
        'service_type' => 'general',
        'source' => 'website',
        'stage' => 'new',
        'priority' => 'low',
    ]);

    $response = $this->actingAs($admin)->delete(route('admin.crm.leads.destroy', $lead));

    $response->assertRedirect(route('admin.crm.index'));
    $this->assertDatabaseMissing('crm_leads', [
        'id' => $lead->id,
    ]);
});
