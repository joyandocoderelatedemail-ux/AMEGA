<?php

use App\Models\Faq;
use App\Models\SrrvApplication;
use App\Models\TicketBooking;
use App\Models\User;

/**
 * "Open" alone is read-only: the desk opens, but starting or saving anything takes Create, and
 * changing what is there takes Edit. (Janis had Open only and could still book a ticket.)
 */
function openOnly(array $permissions): User
{
    $user = User::factory()->create(['role' => 'agent']);
    $user->forceFill(['permissions' => $permissions])->save();

    return $user->refresh();
}

test('with Open only, the ticketing desk opens but no booking can be started or saved', function () {
    $janis = openOnly(['ticketing.view', 'srrv.view', 'visa_assistance.view', 'immigration.view']);

    $this->actingAs($janis)->get(route('ticketing.dashboard'))->assertOk()->assertDontSee('New Ticket Booking');
    $this->actingAs($janis)->get(route('ticketing.tickets.index'))->assertOk();

    $this->actingAs($janis)->get(route('ticketing.tickets.create'))->assertRedirect()
        ->assertSessionHas('error', 'You can open this page but not create anything here. Ask an admin for the Create permission.');
    $this->actingAs($janis)->post(route('ticketing.tickets.store'), ['travel_type' => 'domestic'])->assertSessionHas('error');
    $this->actingAs($janis)->post(route('ticketing.tickets.pending.store'), [])->assertSessionHas('error');
    expect(TicketBooking::withoutGlobalScopes()->count())->toBe(0);

    // The other desks the same: their lists open, new files do not.
    $this->actingAs($janis)->get(route('srrv.dashboard'))->assertOk();
    $this->actingAs($janis)->get(route('srrv.applications.create'))->assertSessionHas('error');
    $this->actingAs($janis)->post(route('srrv.applications.store'), [])->assertSessionHas('error');
    $this->actingAs($janis)->get(route('visa.applications.create'))->assertSessionHas('error');
    $this->actingAs($janis)->post(route('admin.client-sheets.store'), [])->assertSessionHas('error');
});

test('Create lets someone start new files; changing an existing one still takes Edit', function () {
    $creator = openOnly(['srrv.view', 'srrv.create']);

    $this->actingAs($creator)->get(route('srrv.applications.create'))->assertOk();
    $this->actingAs($creator)->post(route('srrv.applications.store'), [
        'service_type' => 'renewal_application', 'visa_class' => 'classic', 'retiree_name' => 'Ramon Cruz', 'retiree_email' => 'ramon@example.com',
        'retiree_phone' => '09170000000', 'date_of_birth' => now()->subYears(62)->format('Y-m-d'), 'nationality' => 'Filipino', 'investment_amount' => 1500,
    ])->assertRedirect()->assertSessionMissing('error');
    $file = SrrvApplication::withoutGlobalScopes()->firstOrFail();

    // Opening it read-only is fine; editing it is not.
    $this->actingAs($creator)->get(route('srrv.applications.edit', [$file, 'preview' => 1]))->assertOk();
    $this->actingAs($creator)->get(route('srrv.applications.edit', $file))
        ->assertSessionHas('error', 'You can open this page but not change anything here. Ask an admin for the Edit permission.');
    $this->actingAs($creator)->put(route('srrv.applications.update', $file), ['retiree_name' => 'Changed'])->assertSessionHas('error');
    expect($file->fresh()->retiree_name)->toBe('Ramon Cruz');
});

test('admin pages: Open shows the page, Create and Edit are separate', function () {
    $viewer = openOnly(['faqs.view']);

    $this->actingAs($viewer)->get(route('admin.faqs.index'))->assertOk();
    $this->actingAs($viewer)->post(route('admin.faqs.store'), ['question' => 'Q?', 'answer' => 'A.'])->assertSessionHas('error');
    expect(Faq::where('question', 'Q?')->exists())->toBeFalse();
});

test('someone who cannot start a booking lands on the ticketing dashboard', function () {
    expect(openOnly(['ticketing.view'])->staffHomeRoute())->toBe('ticketing.dashboard')
        ->and(openOnly(['ticketing.view', 'ticketing.create'])->staffHomeRoute())->toBe('ticketing.tickets.create');
});
