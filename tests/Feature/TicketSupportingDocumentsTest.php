<?php

use App\Models\TicketBooking;
use App\Models\TicketBookingDocument;
use App\Models\User;
use App\Support\DocumentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function supportingTicket(User $officer): TicketBooking
{
    return TicketBooking::withoutGlobalScopes()->create([
        'booking_reference' => 'TKT-DOM-'.uniqid(), 'destination' => 'Cebu', 'departure_date' => now()->addMonth(),
        'contact_name' => 'Juan Dela Cruz', 'contact_email' => 'juan@example.com', 'contact_phone' => '09171234567',
        'created_by' => $officer->id,
    ]);
}

test('the ticket page keeps supporting documents: upload several, download, remove', function () {
    Storage::fake(DocumentStorage::diskName());
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = supportingTicket($officer);

    $this->actingAs($officer)->get(route('ticketing.tickets.show', $ticket))->assertOk()
        ->assertSee('Supporting Documents')->assertSee('No supporting documents yet.')->assertSee('Hotel voucher');

    $this->actingAs($officer)->post(route('ticketing.tickets.supporting-documents.store', $ticket), [
        'document_type' => 'e_ticket', 'note' => 'Outbound',
        'files' => [UploadedFile::fake()->image('ticket-photo.jpg'), UploadedFile::fake()->create('eticket.pdf', 300, 'application/pdf')],
    ])->assertRedirect(route('ticketing.tickets.show', $ticket).'#supporting-documents')->assertSessionHasNoErrors();

    $documents = $ticket->supportingDocuments()->get();
    expect($documents)->toHaveCount(2)
        ->and($documents->pluck('document_type')->unique()->all())->toBe(['e_ticket'])
        ->and($documents->first()->uploaded_by)->toBe($officer->id);
    Storage::disk(DocumentStorage::diskName())->assertExists($documents->first()->file_path);

    $this->actingAs($officer)->get(route('ticketing.tickets.show', $ticket))->assertSee('ticket-photo.jpg')->assertSee('Outbound');
    $this->actingAs($officer)->get(route('ticketing.supporting-documents.download', $documents->first()))->assertOk();

    $this->actingAs($officer)->delete(route('ticketing.tickets.supporting-documents.destroy', [$ticket, $documents->first()]))->assertRedirect();
    expect($ticket->supportingDocuments()->count())->toBe(1);
    Storage::disk(DocumentStorage::diskName())->assertMissing($documents->first()->file_path);
});

test('another officer cannot reach these documents, and bad files are refused', function () {
    Storage::fake(DocumentStorage::diskName());
    $owner = User::factory()->create(['role' => 'ticketing']);
    $other = User::factory()->create(['role' => 'ticketing']);
    $ticket = supportingTicket($owner);
    $this->actingAs($owner)->post(route('ticketing.tickets.supporting-documents.store', $ticket), [
        'document_type' => 'hotel_voucher', 'files' => [UploadedFile::fake()->create('voucher.pdf', 100, 'application/pdf')],
    ]);
    $document = TicketBookingDocument::firstOrFail();

    $this->actingAs($other)->get(route('ticketing.supporting-documents.download', $document))->assertNotFound();
    $this->actingAs($other)->post(route('ticketing.tickets.supporting-documents.store', $ticket), [
        'document_type' => 'other', 'files' => [UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')],
    ])->assertNotFound();

    $this->actingAs($owner)->post(route('ticketing.tickets.supporting-documents.store', $ticket), [
        'document_type' => 'other', 'files' => [UploadedFile::fake()->create('virus.exe', 10)],
    ])->assertSessionHasErrors('files.0');
    $this->actingAs($owner)->post(route('ticketing.tickets.supporting-documents.store', $ticket), ['document_type' => 'bogus', 'files' => []])
        ->assertSessionHasErrors(['document_type', 'files']);

    expect(TicketBookingDocument::count())->toBe(1);
});
