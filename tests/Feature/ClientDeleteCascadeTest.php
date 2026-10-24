<?php

use App\Models\Booking;
use App\Models\ImmigrationClient;
use App\Models\Inquiry;
use App\Models\SrrvApplication;
use App\Models\TicketBooking;
use App\Models\TicketFlightChange;
use App\Models\TravelPackage;
use App\Models\User;
use App\Models\VisaApplication;
use App\Services\ClientRecords;
use App\Support\DocumentStorage;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Facades\Storage;

/**
 * A client with a file at every desk, one of them by another officer, and a different person whose
 * name merely contains the client's.
 *
 * @return array{0: User, 1: User}
 */
function clientWithServices(): array
{
    $client = User::factory()->create([
        'role' => 'client', 'name' => 'Juan Dela Cruz', 'first_name' => 'Juan', 'last_name' => 'Dela Cruz',
        'email' => 'juan@example.com', 'phone' => '09171234567', 'passport_number' => 'P1234567',
    ]);
    $officer = User::factory()->create(['role' => 'srrv']);

    EloquentModel::unguarded(function () use ($client, $officer) {
        $package = TravelPackage::create(['title' => 'Palawan Escape', 'duration' => '5 Days', 'price' => '$1,000', 'rating' => 5,
            'image' => 'images/test.jpg', 'description' => 'Test', 'category' => 'short_haul', 'status' => 'active']);
        Booking::create(['booking_reference' => 'BK-1', 'user_id' => $client->id, 'travel_package_id' => $package->id, 'customer_name' => 'Juan Dela Cruz', 'customer_email' => 'juan@example.com',
            'customer_phone' => '0917', 'travel_date' => now()->addMonth(), 'number_of_passengers' => 1, 'status' => 'pending', 'payment_status' => 'unpaid']);
        $ticket = TicketBooking::withoutGlobalScopes()->create(['booking_reference' => 'TK-1', 'destination' => 'Cebu', 'departure_date' => now()->addMonth(),
            'contact_name' => 'Juan Dela Cruz', 'contact_email' => 'JUAN@example.com', 'contact_phone' => '0917']);
        TicketFlightChange::create(['ticket_booking_id' => $ticket->id, 'type' => 'reschedule']);
        VisaApplication::withoutGlobalScopes()->create(['reference' => 'VA-1', 'service_type' => 'visit_visa', 'client_name' => 'juan dela cruz']);
        $folder = ImmigrationClient::factory()->create(['user_id' => $client->id, 'passport_number' => 'P1234567']);
        Storage::disk(DocumentStorage::diskName())->put('immigration/passport.pdf', 'scan');
        $folder->documents()->create(['document_type' => 'passport', 'file_path' => 'immigration/passport.pdf']);
        // Opened by another officer: still this client's, so it goes too.
        $srrv = SrrvApplication::withoutGlobalScopes()->create(['reference' => 'SRV-1', 'service_type' => 'renewal_application', 'visa_class' => 'classic',
            'status' => 'pending', 'retiree_name' => 'Juan Dela Cruz', 'retiree_email' => 'juan@example.com', 'created_by' => $officer->id]);
        Storage::disk(DocumentStorage::diskName())->put('srrv/SRV-1/police.pdf', 'scan');
        $srrv->documents()->create(['document_type' => 'police_clearance', 'file_path' => 'srrv/SRV-1/police.pdf', 'original_name' => 'police.pdf', 'status' => 'uploaded']);
        Inquiry::create(['name' => 'Juan Dela Cruz', 'email' => 'juan@example.com', 'message' => 'Hi']);

        // A different person: the name only contains the client's, the email differs.
        SrrvApplication::withoutGlobalScopes()->create(['reference' => 'SRV-2', 'service_type' => 'renewal_application', 'visa_class' => 'classic',
            'status' => 'pending', 'retiree_name' => 'Juan Dela Cruz Jr', 'retiree_email' => 'junior@example.com', 'created_by' => $officer->id]);
    });

    return [$client, $officer];
}

test('deleting a client deletes every service recorded for them, and their files', function () {
    Storage::fake(DocumentStorage::diskName());
    [$client] = clientWithServices();
    $admin = User::factory()->create(['role' => 'admin']);

    expect(ClientRecords::summary($client))->toBe([
        'Online bookings' => 1, 'Ticket bookings' => 1, 'Visa applications' => 1, 'Immigration folders' => 1,
        'SRRV applications' => 1, 'Website inquiries' => 1,
    ]);

    $this->actingAs($admin)->delete(route('admin.users.destroy', $client))
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('success', fn ($message) => str_contains($message, '1 Ticket bookings') && str_contains($message, '1 SRRV applications'));

    expect(User::find($client->id))->toBeNull()
        ->and(Booking::count())->toBe(0)
        ->and(TicketBooking::withoutGlobalScopes()->count())->toBe(0)
        ->and(TicketFlightChange::count())->toBe(0)
        ->and(VisaApplication::withoutGlobalScopes()->count())->toBe(0)
        ->and(ImmigrationClient::count())->toBe(0)
        ->and(Inquiry::count())->toBe(0)
        // Only the other person's SRRV file is left.
        ->and(SrrvApplication::withoutGlobalScopes()->pluck('reference')->all())->toBe(['SRV-2']);

    Storage::disk(DocumentStorage::diskName())->assertMissing('immigration/passport.pdf');
    Storage::disk(DocumentStorage::diskName())->assertMissing('srrv/SRV-1/police.pdf');
});

test('only an admin can delete a client who has service records', function () {
    Storage::fake(DocumentStorage::diskName());
    [$client] = clientWithServices();
    $agent = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['users']]);

    $this->actingAs($agent)->delete(route('admin.users.destroy', $client))
        ->assertSessionHas('error', fn ($message) => str_contains($message, 'Only an admin can delete a client with service records'));
    expect(User::find($client->id))->not->toBeNull()->and(Booking::count())->toBe(1);

    // A client with nothing on file can still be deleted by them.
    $empty = User::factory()->create(['role' => 'client', 'name' => 'Nobody Here', 'email' => 'nobody@example.com']);
    $this->actingAs($agent)->delete(route('admin.users.destroy', $empty))->assertRedirect();
    expect(User::find($empty->id))->toBeNull();
});
