<?php

use App\Models\Inquiry;
use App\Models\TicketBooking;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\DateRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 */
function staffTicket(User $owner, array $overrides = []): TicketBooking
{
    $createdAt = $overrides['created_at'] ?? null;
    unset($overrides['created_at']);

    $ticket = TicketBooking::withoutGlobalScopes()->create(array_merge([
        'booking_reference' => 'TKT-'.fake()->unique()->numerify('########'),
        'travel_type' => 'domestic',
        'package_type' => 'without_package',
        'origin' => 'Manila (MNL)',
        'destination' => 'Cebu (CEB)',
        'departure_date' => now()->addDays(20),
        'total_passengers' => 1,
        'contact_name' => 'Maria Santos',
        'total_amount' => 10000,
        'amount_paid' => 4000,
        'status' => 'pending',
        'created_by' => $owner->id,
    ], $overrides));

    // created_at is not fillable, so an older opening date is set directly.
    if ($createdAt) {
        $ticket->forceFill(['created_at' => $createdAt])->save();
    }

    return $ticket;
}

test('a date range reads presets and custom dates from the query string', function () {
    Carbon::setTestNow('2026-09-26 10:00:00');

    $thisMonth = DateRange::fromRequest(Request::create('/', 'GET', ['range' => 'this_month']));
    expect($thisMonth->from->toDateString())->toBe('2026-09-01')
        ->and($thisMonth->to->toDateString())->toBe('2026-09-30')
        ->and($thisMonth->label())->toBe('Sep 1 – Sep 30, 2026');

    $custom = DateRange::fromRequest(Request::create('/', 'GET', ['from' => '2026-09-20', 'to' => '2026-09-10']));
    expect($custom->preset)->toBe('custom')
        ->and($custom->from->toDateString())->toBe('2026-09-10')
        ->and($custom->to->toDateString())->toBe('2026-09-20')
        ->and($custom->query())->toBe(['from' => '2026-09-10', 'to' => '2026-09-20']);

    expect(DateRange::fromRequest(Request::create('/', 'GET', ['range' => 'nonsense']))->isAllTime())->toBeTrue()
        ->and(DateRange::fromRequest(Request::create('/', 'GET', ['from' => 'not-a-date']))->isAllTime())->toBeTrue();

    Carbon::setTestNow();
});

test('the dashboard counts only what happened in the chosen range', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    Inquiry::create(['name' => 'Old inquiry', 'email' => 'old@example.com', 'message' => 'Hi'])
        ->forceFill(['created_at' => now()->subYear()])->save();
    Inquiry::create(['name' => 'New inquiry', 'email' => 'new@example.com', 'message' => 'Hello']);

    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('All time')
        ->assertSee('Old inquiry')
        ->assertSee('New inquiry');

    $this->actingAs($admin)->get(route('admin.dashboard', ['range' => '7d']))
        ->assertOk()
        ->assertSee('Last 7 days')
        ->assertSee('New inquiry')
        ->assertDontSee('Old inquiry');
});

test('admins can search staff on the dashboard and see what each did in the period', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $ana = User::factory()->create(['role' => 'ticketing', 'name' => 'Ana Officer', 'email' => 'ana@amega.test']);
    $ben = User::factory()->create(['role' => 'visa_assistance', 'name' => 'Ben Officer', 'email' => 'ben@amega.test']);

    staffTicket($ana, ['total_amount' => 12000]);
    staffTicket($ana, ['total_amount' => 8000, 'status' => 'issued', 'issued_by' => $ana->id, 'issued_at' => now()]);
    staffTicket($ana, ['created_at' => now()->subYear()]);

    $response = $this->actingAs($admin)->get(route('admin.dashboard', ['range' => '30d', 'staff' => 'Ana']));

    $response->assertOk()
        ->assertSee('Staff Activity')
        ->assertSee('Ana Officer')
        ->assertDontSee('Ben Officer')
        ->assertSee('2 tickets')
        ->assertSee('&#8369;20,000', false)
        ->assertSee(route('admin.agents.show', ['agent' => $ana, 'range' => '30d']), false);

    $this->actingAs($admin)->get(route('admin.dashboard', ['staff_role' => 'visa_assistance']))
        ->assertSee('Ben Officer')
        ->assertDontSee('Ana Officer');
});

test('desk officers do not see the staff section on the dashboard', function () {
    $agent = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['dashboard', 'bookings']]);

    $this->actingAs($agent)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee('Staff Activity');
});

test('an admin can open a staff member and check their files and activity in a range', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $ana = User::factory()->create(['role' => 'ticketing', 'name' => 'Ana Officer']);
    $recent = staffTicket($ana, ['booking_reference' => 'TKT-RECENT']);
    staffTicket($ana, ['booking_reference' => 'TKT-OLDFILE', 'created_at' => now()->subYear()]);

    $this->actingAs($ana);
    ActivityLogger::log('Ticketing', 'CREATE', 'Created ticket booking TKT-RECENT');

    $this->actingAs($admin)->get(route('admin.agents.show', ['agent' => $ana, 'range' => '30d']))
        ->assertOk()
        ->assertSee('Ana Officer')
        ->assertSee('TKT-RECENT')
        ->assertDontSee('TKT-OLDFILE')
        ->assertSee('Created ticket booking TKT-RECENT')
        ->assertSee(route('ticketing.tickets.show', $recent), false);

    $this->actingAs($admin)->get(route('admin.agents.show', $ana))
        ->assertSee('TKT-OLDFILE');
});

test('only staff accounts have an activity page, and only admins can open it', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $client = User::factory()->create(['role' => 'client']);
    $officer = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($admin)->get(route('admin.agents.show', $client))->assertNotFound();
    $this->actingAs($officer)->get(route('admin.agents.show', $officer))->assertRedirect();
});
