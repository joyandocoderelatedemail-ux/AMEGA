<?php

use App\Models\ImmigrationClient;
use App\Models\Inquiry;
use App\Models\User;
use App\Support\DateRange;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The date range on every list page: a period (Today, Last 7 days...) or From / To dates, read
 * by App\Support\DateRange and applied to each list's own date.
 */
function inquiryOn(string $name, string $date): Inquiry
{
    $inquiry = Inquiry::create(['name' => $name, 'email' => strtolower($name).'@example.com', 'phone' => '0917', 'message' => 'Hello', 'service_requested' => 'Tours', 'status' => 'pending']);
    $inquiry->forceFill(['created_at' => Carbon::parse($date), 'read_at' => now()])->saveQuietly();

    return $inquiry;
}

test('a list keeps only the records in the range, both days included', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    inquiryOn('Zebedeeq', '2026-09-30 23:00');
    inquiryOn('Quillonx', '2026-10-05 09:00');
    inquiryOn('Marvadoz', '2026-10-10 22:00');
    inquiryOn('Tuppenqy', '2026-10-11 08:00');

    $this->actingAs($admin)->get(route('admin.inquiries.index', ['from' => '2026-10-01', 'to' => '2026-10-10']))->assertOk()
        ->assertSee('Quillonx')->assertSee('Marvadoz')->assertDontSee('Zebedeeq')->assertDontSee('Tuppenqy')
        ->assertSee('name="from"', false)->assertSee('value="2026-10-01"', false);

    // No range: everything.
    $this->actingAs($admin)->get(route('admin.inquiries.index'))->assertSee('Zebedeeq')->assertSee('Tuppenqy');
});

test('a period such as Today is applied on its own, and reversed dates are swapped', function () {
    $today = DateRange::fromRequest(Request::create('/', 'GET', ['range' => 'today']));
    expect($today->contains(now()))->toBeTrue()->and($today->contains(now()->subDay()))->toBeFalse();

    $swapped = DateRange::fromRequest(Request::create('/', 'GET', ['from' => '2026-10-10', 'to' => '2026-10-01']));
    expect($swapped->from->toDateString())->toBe('2026-10-01')->and($swapped->to->toDateString())->toBe('2026-10-10');

    // A date that cannot be read is ignored, not an error.
    expect(DateRange::fromRequest(Request::create('/', 'GET', ['from' => 'yesterday-ish']))->isAllTime())->toBeTrue();
});

test('every list page offers the date range', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $folder = ImmigrationClient::factory()->create();

    $pages = [
        route('admin.activity-logs.index'), route('admin.agents.index'), route('admin.users.index'), route('admin.bookings.index'),
        route('admin.corporates.index'), route('admin.inquiries.index'), route('admin.crm.index'),
        route('admin.ticket-approvals.index'), route('admin.srrv-approvals.index'), route('admin.visa-approvals.index'), route('admin.immigration-approvals.index'),
        route('admin.immigration.cases'), route('admin.client-sheets.applications', ['service' => '13a_spouse']),
        route('cashier.dashboard'), route('cashier.paid'),
        route('srrv.applications.index'), route('srrv.renewals.index'), route('srrv.services', 'classic'), route('srrv.services', 'restamping'),
        route('ticketing.tickets.index'), route('ticketing.quotations.index'),
        route('visa.applications.index'), route('visa.tourist-visa.index'), route('visa.services', 'visit_visa'),
    ];

    foreach ($pages as $url) {
        $html = $this->actingAs($admin)->get($url)->assertOk()->getContent();
        expect(str_contains($html, 'name="range"') && str_contains($html, 'name="from"') && str_contains($html, 'name="to"'))
            ->toBeTrue("No date range on {$url}");
    }
    expect($folder->exists)->toBeTrue();
});

test('the approval queues filter by when a file was sent or decided', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get(route('admin.ticket-approvals.index', ['range' => 'today']))->assertOk()->assertSee('Sent');
    $this->actingAs($admin)->get(route('admin.ticket-approvals.index', ['tab' => 'approved', 'range' => 'today']))->assertOk()->assertSee('Decided');
});
