<?php

use App\Models\ImmigrationCategory;
use App\Models\ImmigrationClient;
use App\Models\ImmigrationPricingTier;
use App\Models\ImmigrationQuotation;
use App\Models\User;
use App\Notifications\ImmigrationQuotationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function immigrationQuoteAgent(array $permissions = ['immigration.view', 'immigration.create', 'immigration.edit']): User
{
    return User::factory()->create(['role' => 'agent', 'permissions' => $permissions]);
}

function immigrationQuotePayload(array $overrides = []): array
{
    return array_merge([
        'client_name' => 'John Smith',
        'client_email' => 'john@example.com',
        'client_phone' => '09170001111',
        'nationality' => 'American',
        'passport_number' => 'US1234567',
        'valid_until' => today()->addDays(15)->toDateString(),
        'discount' => '500',
        'notes' => 'Processing takes about 3 weeks.',
        'items' => [
            ['service_key' => '13a_spouse', 'description' => '13/A Spouse Visa - BI fees', 'quantity' => 1, 'unit_price' => '15000'],
            ['service_key' => '', 'description' => 'AMEGA service fee', 'quantity' => 2, 'unit_price' => '2500.50'],
        ],
    ], $overrides);
}

function savedImmigrationQuote(User $agent, array $overrides = []): ImmigrationQuotation
{
    test()->actingAs($agent)->post(route('admin.immigration.quotations.store'), immigrationQuotePayload($overrides))->assertSessionHasNoErrors();

    return ImmigrationQuotation::withoutGlobalScopes()->latest('id')->firstOrFail();
}

test('the counter has a Quotations tab and a New quotation form with the BI rates', function () {
    $category = ImmigrationCategory::create(['name' => 'Tourist Visa Extension', 'slug' => 'visa-extension', 'sort_order' => 1, 'is_active' => true]);
    ImmigrationPricingTier::create(['immigration_category_id' => $category->id, 'extension_label' => '1st extension', 'duration_label' => '29 days', 'process_type' => 'regular', 'payment_method' => 'cash', 'price' => 3630, 'is_active' => true, 'needs_review' => false, 'sort_order' => 1]);
    $agent = immigrationQuoteAgent();

    $this->actingAs($agent)->get(route('admin.immigration.dashboard'))->assertOk()->assertSee('Quotations');
    $this->actingAs($agent)->get(route('admin.immigration.quotations.create'))->assertOk()
        ->assertSee('New quotation')->assertSee('1st extension')->assertSee('3630')->assertSee('visa_extension', false);
});

test('a quotation is saved with its number, lines and totals', function () {
    $agent = immigrationQuoteAgent();
    $quotation = savedImmigrationQuote($agent);

    expect($quotation->quote_number)->toBe(sprintf('IQ-%s-%04d', now()->format('Y'), $quotation->id))
        ->and($quotation->status)->toBe('open')
        ->and($quotation->created_by)->toBe($agent->id)
        ->and((float) $quotation->subtotal)->toBe(20001.0)
        ->and((float) $quotation->discount)->toBe(500.0)
        ->and((float) $quotation->total)->toBe(19501.0)
        ->and($quotation->items)->toHaveCount(2)
        ->and((float) $quotation->items[1]->amount)->toBe(5001.0);

    $this->actingAs($agent)->get(route('admin.immigration.quotations.show', $quotation))->assertOk()
        ->assertSee($quotation->quote_number)->assertSee('JOHN SMITH')->assertSee('₱19,501.00')->assertSee('Open file');
    $this->actingAs($agent)->get(route('admin.immigration.quotations.index'))->assertOk()->assertSee($quotation->quote_number);
});

test('a quotation needs a client name and at least one line, and the discount cannot exceed the subtotal', function () {
    $agent = immigrationQuoteAgent();

    $this->actingAs($agent)->post(route('admin.immigration.quotations.store'), immigrationQuotePayload(['client_name' => '', 'items' => []]))
        ->assertSessionHasErrors(['client_name', 'items']);
    $this->actingAs($agent)->post(route('admin.immigration.quotations.store'), immigrationQuotePayload(['discount' => '999999']))
        ->assertSessionHasErrors('discount');
    $this->actingAs($agent)->post(route('admin.immigration.quotations.store'), immigrationQuotePayload(['valid_until' => today()->subDay()->toDateString()]))
        ->assertSessionHasErrors('valid_until');

    expect(ImmigrationQuotation::withoutGlobalScopes()->count())->toBe(0);
});

test('the PDF prints on the letterhead', function () {
    $agent = immigrationQuoteAgent();
    $quotation = savedImmigrationQuote($agent);

    $response = $this->actingAs($agent)->get(route('admin.immigration.quotations.pdf', $quotation))->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->getContent())->toStartWith('%PDF');
});

test('the quotation can be emailed to the client with the PDF', function () {
    Notification::fake();
    $agent = immigrationQuoteAgent();
    $quotation = savedImmigrationQuote($agent);

    $this->actingAs($agent)->post(route('admin.immigration.quotations.email', $quotation))->assertSessionHas('success');

    Notification::assertSentOnDemand(ImmigrationQuotationNotification::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === ['john@example.com' => 'JOHN SMITH']);
    expect($quotation->fresh()->emailed_at)->not->toBeNull();

    $mail = (new ImmigrationQuotationNotification($quotation, $agent->name))->toMail((object) []);
    expect($mail->rawAttachments[0]['name'])->toBe('Quotation-'.$quotation->quote_number.'.pdf')
        ->and(implode(' ', $mail->introLines))->toContain('13/A SPOUSE VISA - BI FEES');
});

test('without an email address the quotation is not sent', function () {
    Notification::fake();
    $agent = immigrationQuoteAgent();
    $quotation = savedImmigrationQuote($agent, ['client_email' => '']);

    $this->actingAs($agent)->post(route('admin.immigration.quotations.email', $quotation))->assertSessionHas('error');
    Notification::assertNothingSent();
});

test('an open quotation can be edited; an accepted one cannot until reopened', function () {
    $agent = immigrationQuoteAgent();
    $quotation = savedImmigrationQuote($agent);

    $this->actingAs($agent)->put(route('admin.immigration.quotations.update', $quotation), immigrationQuotePayload(['discount' => '0', 'items' => [
        ['service_key' => 'ecc', 'description' => 'ECC', 'quantity' => 1, 'unit_price' => '1500'],
    ]]))->assertSessionHasNoErrors();
    expect((float) $quotation->fresh()->total)->toBe(1500.0)->and($quotation->items()->count())->toBe(1);

    $this->actingAs($agent)->post(route('admin.immigration.quotations.status', $quotation), ['status' => 'accepted']);
    expect($quotation->fresh()->status)->toBe('accepted')->and($quotation->fresh()->accepted_at)->not->toBeNull();

    $this->actingAs($agent)->put(route('admin.immigration.quotations.update', $quotation), immigrationQuotePayload())->assertSessionHas('error');
    expect((float) $quotation->fresh()->total)->toBe(1500.0);

    $this->actingAs($agent)->post(route('admin.immigration.quotations.status', $quotation), ['status' => 'open']);
    $this->actingAs($agent)->get(route('admin.immigration.quotations.edit', $quotation))->assertOk();
});

test('an open quotation past its date shows as expired', function () {
    $agent = immigrationQuoteAgent();
    $quotation = savedImmigrationQuote($agent);
    $quotation->forceFill(['valid_until' => today()->subDay()])->save();

    expect($quotation->fresh()->statusLabel())->toBe('Expired');
    $this->actingAs($agent)->get(route('admin.immigration.quotations.index', ['status' => 'expired']))->assertSee($quotation->quote_number);
    $this->actingAs($agent)->get(route('admin.immigration.quotations.index', ['status' => 'open']))->assertDontSee($quotation->quote_number);
});

test('Open file accepts the quotation and opens the service with the quoted fee filled in', function () {
    $agent = immigrationQuoteAgent();
    $folder = ImmigrationClient::create(['last_name' => 'Smith', 'given_name' => 'John', 'passport_number' => 'US1234567', 'created_by' => $agent->id]);
    $quotation = savedImmigrationQuote($agent, ['immigration_client_id' => $folder->id]);
    $line = $quotation->items->firstWhere('service_key', '13a_spouse');

    $response = $this->actingAs($agent)->post(route('admin.immigration.quotations.start', [$quotation, $line]));
    $response->assertRedirect(route('admin.client-sheets.edit', ['client_sheet' => $folder->id, 'service' => '13a_spouse', 'quote_item' => $line->id]));
    expect($quotation->fresh()->status)->toBe('accepted');

    $this->actingAs($agent)->get($response->headers->get('Location'))->assertOk()
        ->assertSee('name="petition[fee]" value="15000.00"', false);
});

test('for a walk-in, Open file starts a new folder named from the quotation, with the fee', function () {
    $agent = immigrationQuoteAgent();
    $quotation = savedImmigrationQuote($agent);
    $line = $quotation->items->firstWhere('service_key', '13a_spouse');

    $location = $this->actingAs($agent)->post(route('admin.immigration.quotations.start', [$quotation, $line]))->headers->get('Location');

    expect($location)->toContain('client-sheets/create')->toContain('quote_item='.$line->id)->toContain('passport=US1234567');
    $this->actingAs($agent)->get($location)->assertOk()->assertSee('value="15000.00"', false)->assertSee('US1234567');
});

test('a line that is not a counter service, or a declined quotation, opens nothing', function () {
    $agent = immigrationQuoteAgent();
    $quotation = savedImmigrationQuote($agent);
    $feeLine = $quotation->items->firstWhere('service_key', null);

    $this->actingAs($agent)->post(route('admin.immigration.quotations.start', [$quotation, $feeLine]))->assertSessionHas('error');

    $this->actingAs($agent)->post(route('admin.immigration.quotations.status', $quotation), ['status' => 'declined']);
    $this->actingAs($agent)->post(route('admin.immigration.quotations.start', [$quotation, $quotation->items->first()]))->assertSessionHas('error');
    expect($quotation->fresh()->status)->toBe('declined');
});

test('the client finder returns folders and registered clients', function () {
    $agent = immigrationQuoteAgent();
    ImmigrationClient::create(['last_name' => 'Rivera', 'given_name' => 'Ana', 'passport_number' => 'P7654321', 'email' => 'ana@example.com']);
    User::factory()->create(['role' => 'client', 'first_name' => 'Ana', 'last_name' => 'Rivers', 'name' => 'Ana Rivers', 'email' => 'rivers@example.com']);

    $matches = $this->actingAs($agent)->getJson(route('admin.immigration.quotations.clients', ['q' => 'Ana']))->assertOk()->json();

    expect(collect($matches)->pluck('kind')->all())->toContain('folder')->toContain('client')
        ->and(collect($matches)->firstWhere('kind', 'folder')['client_email'])->toBe('ana@example.com');
});

test('an agent only sees their own quotations, and only an admin can delete one', function () {
    $mine = immigrationQuoteAgent();
    $other = immigrationQuoteAgent();
    $quotation = savedImmigrationQuote($mine);

    $this->actingAs($other)->get(route('admin.immigration.quotations.show', $quotation))->assertNotFound();
    $this->actingAs($other)->get(route('admin.immigration.quotations.index'))->assertDontSee($quotation->quote_number);

    $this->actingAs($mine)->delete(route('admin.immigration.quotations.destroy', $quotation));
    expect(ImmigrationQuotation::withoutGlobalScopes()->whereKey($quotation->id)->exists())->toBeTrue();

    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->delete(route('admin.immigration.quotations.destroy', $quotation))->assertRedirect(route('admin.immigration.quotations.index'));
    expect(ImmigrationQuotation::withoutGlobalScopes()->whereKey($quotation->id)->exists())->toBeFalse();
});

test('creating a quotation needs the Create permission', function () {
    $viewer = immigrationQuoteAgent(['immigration.view']);

    $this->actingAs($viewer)->post(route('admin.immigration.quotations.store'), immigrationQuotePayload());
    expect(ImmigrationQuotation::withoutGlobalScopes()->count())->toBe(0);
});

test('what is typed is kept in capitals, except the email', function () {
    $agent = immigrationQuoteAgent();
    $quotation = savedImmigrationQuote($agent, ['client_name' => 'john smith', 'nationality' => 'american', 'passport_number' => 'us1234567', 'client_email' => 'John@Example.com', 'notes' => 'bring the passport']);

    expect($quotation)->client_name->toBe('JOHN SMITH')->nationality->toBe('AMERICAN')->passport_number->toBe('US1234567')
        ->notes->toBe('BRING THE PASSPORT')->client_email->toBe('John@Example.com')
        ->and($quotation->items->first()->description)->toBe('13/A SPOUSE VISA - BI FEES');

    $this->actingAs($agent)->get(route('admin.immigration.quotations.create'))->assertOk()->assertSee('data-uppercase', false);
});
