<?php

use App\Models\Inquiry;
use App\Models\Service;
use App\Models\User;

test('a submitted inquiry returns the visitor to the form with a confirmation', function () {
    $response = $this->from(route('home'))->post(route('contact.submit'), [
        'account_category' => 'Individual',
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'email' => 'juan@example.com',
        'phone' => '639123456789',
        'number_of_pax' => 3,
        'nationality' => 'Philippines',
        'message' => 'Planning a trip to Palawan in December.',
    ]);

    $response->assertRedirect(route('home').'#contact');

    expect(Inquiry::where('email', 'juan@example.com')->exists())->toBeTrue();

    $this->get(route('home'))
        ->assertSee('Thank you for your message! Our travel agents will get back to you shortly.');
});

test('a rejected inquiry shows why and keeps what the visitor typed', function () {
    $this->from(route('contact'))->post(route('contact.submit'), [
        'first_name' => 'Juan',
        'email' => 'not-an-email',
        'message' => '',
    ])->assertRedirect(route('contact').'#contact');

    $this->get(route('contact'))
        ->assertSee('Your inquiry was not sent')
        ->assertSee('value="Juan"', false);

    expect(Inquiry::count())->toBe(0);
});

test('an inquiry keeps the service category, number of pax and nationality', function () {
    $this->post(route('contact.submit'), [
        'account_category' => 'Airfare',
        'first_name' => 'Anna',
        'last_name' => 'Smith',
        'email' => 'anna@example.com',
        'phone' => '61412345678',
        'number_of_pax' => 4,
        'nationality' => 'Australia',
        'message' => 'Manila to Sydney return.',
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('inquiries', [
        'email' => 'anna@example.com',
        'service_requested' => 'Airfare',
        'number_of_pax' => 4,
        'nationality' => 'Australia',
    ]);
});

test('an inquiry needs a number of pax and a listed nationality', function () {
    $this->post(route('contact.submit'), [
        'first_name' => 'Anna',
        'email' => 'anna@example.com',
        'number_of_pax' => 0,
        'nationality' => 'Atlantis',
        'message' => 'Hello',
    ])->assertSessionHasErrors(['number_of_pax', 'nationality']);

    expect(Inquiry::count())->toBe(0);
});

test('the contact form offers every active core service and airfare as a category', function () {
    Service::create(['title' => 'Visa Assistance', 'short_description' => 'x', 'icon' => 'globe', 'is_active' => true]);
    Service::create(['title' => 'Hidden Service', 'short_description' => 'x', 'icon' => 'globe', 'is_active' => false]);

    $this->get(route('contact'))
        ->assertOk()
        ->assertSee('value="Visa Assistance"', false)
        ->assertSee('value="Airfare"', false)
        ->assertDontSee('Hidden Service')
        ->assertSee('name="number_of_pax"', false)
        ->assertSee('name="nationality"', false);
});

test('the Plan Your Trip button leads to the contact page', function () {
    $this->get(route('home'))
        ->assertSee('href="'.route('contact').'"', false)
        ->assertSee('Plan Your Trip');
});

test('the admin inquiries list shows the category, pax and nationality', function () {
    Inquiry::create([
        'name' => 'Anna Smith',
        'email' => 'anna@example.com',
        'number_of_pax' => 4,
        'nationality' => 'Australia',
        'service_requested' => 'Airfare',
        'message' => 'Manila to Sydney return.',
    ]);

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('admin.inquiries.index'))
        ->assertOk()
        ->assertSee('Airfare')
        ->assertSee('4 pax')
        ->assertSee('Australia');
});

function inquiryPayload(array $overrides = []): array
{
    return $overrides + [
        'account_category' => 'Individual', 'first_name' => 'Maria', 'last_name' => 'Santos', 'email' => 'maria@example.com',
        'phone' => '09171234567', 'number_of_pax' => 2, 'nationality' => 'Philippines', 'message' => 'Boracay in May.',
    ];
}

test('the form sends in the background and the visitor stays on the page', function () {
    $this->get(route('contact'))->assertOk()
        ->assertSee('x-data="contactForm()"', false)->assertSee('inputmode="numeric"', false)->assertSee('pattern="[0-9]{7,15}"', false);

    $this->postJson(route('contact.submit'), inquiryPayload())
        ->assertOk()->assertJson(['message' => 'Thank you for your message! Our travel agents will get back to you shortly.']);

    expect(Inquiry::where('email', 'maria@example.com')->first()->read_at)->toBeNull();
});

test('the phone number must be numbers only', function (string $phone) {
    $this->postJson(route('contact.submit'), inquiryPayload(['phone' => $phone]))
        ->assertUnprocessable()->assertJsonValidationErrors('phone');
})->with(['+63 917 123 4567', '0917-123-4567', 'call me', '12345', '']);

test('a new inquiry shows a red badge and the bell in the admin until the inbox is opened', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->postJson(route('contact.submit'), inquiryPayload())->assertOk();
    $this->postJson(route('contact.submit'), inquiryPayload(['email' => 'second@example.com']))->assertOk();

    $this->actingAs($admin)->getJson(route('admin.notifications'))->assertOk()
        ->assertJsonPath('total', 2)->assertJsonPath('inquiries', 2)->assertJsonPath('latest.0.title', 'New inquiry from Maria Santos');

    $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()
        ->assertSee('data-notify-count="inquiries"', false)->assertSee('aria-label="2 new"', false)->assertSee('x-data="adminBell(', false);

    // Opening the inbox reads them: marked New this once, and the count drops to zero.
    $this->actingAs($admin)->get(route('admin.inquiries.index'))->assertOk()->assertSee('>New</span>', false);
    $this->actingAs($admin)->getJson(route('admin.notifications'))->assertJsonPath('total', 0);
});

test('staff without the inquiries page get no inquiry badge or bell', function () {
    $this->postJson(route('contact.submit'), inquiryPayload())->assertOk();
    $agent = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['users']]);

    $this->actingAs($agent)->getJson(route('admin.notifications'))->assertJsonPath('total', 0)->assertJsonPath('latest', []);
    $this->actingAs($agent)->get(route('admin.users.index'))->assertOk()->assertDontSee('adminBell(', false);
});

test('staff can tick Contacted and leave remarks on an inquiry', function () {
    $staff = User::factory()->create(['role' => 'admin', 'name' => 'Ana Staff']);
    $inquiry = Inquiry::create(['name' => 'Juan', 'email' => 'j@example.com', 'message' => 'Hi', 'status' => 'pending']);

    $this->actingAs($staff)->patch(route('admin.inquiries.update', $inquiry), ['contacted' => 1, 'remarks' => 'Called, will email the quote'])
        ->assertRedirect()->assertSessionHasNoErrors();

    $inquiry->refresh();
    expect($inquiry->contacted_at)->not->toBeNull()
        ->and($inquiry->contacted_by)->toBe($staff->id)
        ->and($inquiry->remarks)->toBe('Called, will email the quote');

    $this->actingAs($staff)->get(route('admin.inquiries.index'))->assertOk()
        ->assertSee('Client contacted')->assertSee('Called, will email the quote')->assertSee('by Ana Staff');

    // Saving again with it still ticked keeps the original time and person.
    $first = $inquiry->contacted_at;
    $this->actingAs($staff)->patch(route('admin.inquiries.update', $inquiry), ['contacted' => 1, 'remarks' => 'Quote sent']);
    expect($inquiry->fresh()->contacted_at->equalTo($first))->toBeTrue()->and($inquiry->fresh()->remarks)->toBe('Quote sent');

    // Unticking clears who and when, and keeps the remarks.
    $this->actingAs($staff)->patch(route('admin.inquiries.update', $inquiry), ['remarks' => 'Quote sent']);
    expect($inquiry->fresh()->contacted_at)->toBeNull()->and($inquiry->fresh()->contacted_by)->toBeNull()->and($inquiry->fresh()->remarks)->toBe('Quote sent');
});

test('an inquiry keeps the category and the specific service apart, and the admin list shows both', function () {
    $this->post(route('contact.submit'), [
        'account_category' => 'Immigration Services',
        'service_detail' => 'Visa Extensions',
        'first_name' => 'Ana', 'last_name' => 'Reyes', 'email' => 'ana@example.com',
        'phone' => '09171234567', 'number_of_pax' => 1, 'nationality' => 'Philippines',
        'message' => 'Hello AMEGA Team',
    ])->assertSessionHasNoErrors();

    $inquiry = Inquiry::firstOrFail();
    expect($inquiry->service_requested)->toBe('Immigration Services')->and($inquiry->service_detail)->toBe('Visa Extensions');

    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->get(route('admin.inquiries.index'))->assertOk()
        ->assertSee('Immigration Services')->assertSee('Visa Extensions');
});
