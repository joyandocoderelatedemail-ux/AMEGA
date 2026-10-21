<?php

use App\Models\ImmigrationClient;
use App\Models\User;
use App\Notifications\ImmigrationApprovalRequestedNotification;
use App\Support\ImmigrationCases;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

/**
 * What the browser would send back for the folder form as it is rendered: every field with its
 * value, checked boxes and radios only, the selected option of each select. Uploads are left out.
 *
 * @return array<string, mixed>
 */
function formAsRendered(string $html): array
{
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
    $xpath = new DOMXPath($dom);
    $form = $xpath->query('//form[.//input[@name="_method" and @value="PUT"]]')->item(0);
    $pairs = [];

    foreach ($xpath->query('.//input[@name] | .//select[@name] | .//textarea[@name]', $form) as $field) {
        $name = $field->getAttribute('name');
        $type = strtolower($field->getAttribute('type'));

        if (in_array($type, ['file', 'submit', 'button'], true)) {
            continue;
        }
        if (in_array($type, ['checkbox', 'radio'], true) && ! $field->hasAttribute('checked')) {
            continue;
        }

        $value = match ($field->nodeName) {
            'textarea' => $field->textContent,
            'select' => (function () use ($xpath, $field) {
                $selected = $xpath->query('.//option[@selected]', $field)->item(0) ?? $xpath->query('.//option', $field)->item(0);

                return $selected?->getAttribute('value') ?? '';
            })(),
            default => $field->getAttribute('value'),
        };
        $pairs[] = urlencode($name).'='.urlencode($value);
    }

    parse_str(implode('&', $pairs), $data);

    return $data;
}

dataset('services', [
    'ECC' => ['ecc', 'ecc', ['petition' => ['details' => ['ecc_kind' => 'ecc']]]],
    '13/A Spouse' => ['13a_spouse', 'spouse', ['petition' => ['details' => ['petitioner_last' => 'Santos', 'petitioner_first' => 'Maria']]]],
    '13/A Permanent' => ['13a_permanent', 'permanent', ['petition' => ['details' => ['petitioner_last' => 'Santos', 'petitioner_first' => 'Maria']]]],
    'ACR I-Card Renewal' => ['permanent_13a_series', 'renewal', ['petition' => ['details' => ['acr_number' => 'E123456']]]],
    'Dual Citizenship' => ['dual_citizenship', 'dual', ['petition' => ['details' => ['name_ph' => 'Kim Minjun']]]],
    'Visa Downgrading' => ['visa_downgrading', 'downgrade', ['petition' => ['details' => ['visa_category' => 'employment']]]],
    'Visa Overstaying' => ['visa_overstaying', 'overstay', ['overstay' => ['expired_on' => '2026-09-01']]],
    'Re Stamping Amendment' => ['re_stamping_amendment', 'restamping', ['restamping' => ['applications' => ['transfer']]]],
    'Visa Extension' => ['visa_extension', 'extensions', ['extensions' => [1 => ['extension_date' => '2026-10-15', 'amount_paid' => 2930, 'duration' => '29 days']]]],
]);

/** The approval status of what the agent saved for this service. */
function savedApproval(ImmigrationClient $sheet, string $kind): ?string
{
    return $kind === 'extensions'
        ? $sheet->fresh()->extensionAt(1)?->approval_status
        : collect(ImmigrationCases::forClient($sheet->fresh()))->firstWhere('kind', $kind)['approval'] ?? null;
}

test('an approved file the agent saves again unchanged stays approved, and shows Print instead of Save', function (string $service, string $kind, array $payload) {
    Notification::fake();
    $admin = User::factory()->create(['role' => 'admin']);
    $agent = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['immigration']]);
    $sheet = ImmigrationClient::create(['last_name' => 'Kim', 'given_name' => 'Minjun', 'passport_number' => 'M1234567', 'nationality' => 'Korean', 'visa_expiry_date' => '2026-10-21']);
    $edit = route('admin.client-sheets.edit', array_filter(['client_sheet' => $sheet->id, 'service' => $service === 'visa_extension' ? null : $service]));

    // Both saves send the form as it is rendered, with the agent's entries on the first one.
    $submit = fn (array $entries) => $this->actingAs($agent)->put(
        route('admin.client-sheets.update', $sheet),
        array_replace_recursive(formAsRendered($this->actingAs($agent)->get($edit)->getContent()), ['then' => 'stay', 'acknowledged' => 1], $entries),
    );

    $submit($payload)->assertSessionHasNoErrors();
    expect(savedApproval($sheet, $kind))->toBe('pending');

    $caseId = $kind === 'extensions' ? $sheet->id : collect(ImmigrationCases::forClient($sheet->fresh()))->firstWhere('kind', $kind)['id'];
    $this->actingAs($admin)->post(route('admin.immigration-approvals.approve', [$kind, $caseId]), ['admin_acknowledged' => 1])->assertSessionHas('success');
    expect(savedApproval($sheet, $kind))->toBe('approved');

    // The last step offers Print for the approved file.
    $html = $this->actingAs($agent)->get($edit)->assertOk()->assertSee('This file is approved.')->assertSee('changed: false', false)->getContent();

    // Saving exactly what the form shows changes nothing, so it stays approved and nobody is emailed again.
    expect($html)->toContain('This file is approved.');
    // Fields Alpine fills in the browser (the ECC kind, the visa category, the lapse date) are sent again as they are.
    $submit($payload)->assertSessionHasNoErrors();
    expect(savedApproval($sheet, $kind))->toBe('approved');
    Notification::assertSentToTimes($admin, ImmigrationApprovalRequestedNotification::class, 1);
})->with('services');

test('changing an approved file sends it back to the admin', function () {
    Notification::fake();
    $admin = User::factory()->create(['role' => 'admin']);
    $agent = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['immigration']]);
    $sheet = ImmigrationClient::create(['last_name' => 'Kim', 'given_name' => 'Minjun', 'passport_number' => 'M1234567']);
    $save = fn (array $petition) => $this->actingAs($agent)->put(route('admin.client-sheets.update', $sheet), [
        'service' => 'ecc', 'last_name' => 'Kim', 'given_name' => 'Minjun', 'then' => 'stay', 'petition' => ['details' => ['ecc_kind' => 'ecc']] + $petition,
    ]);

    $save(['fee' => '1000', 'payment_type' => 'none']);
    $case = $sheet->fresh()->openPetition('ecc');
    $this->actingAs($admin)->post(route('admin.immigration-approvals.approve', ['ecc', $case->id]), ['admin_acknowledged' => 1]);

    $save(['fee' => '1500', 'payment_type' => 'none'])->assertSessionHas('success', fn (string $m) => str_contains($m, 'Sent to the admin for approval.'));
    expect($case->fresh()->approval_status)->toBe('pending');
    Notification::assertSentToTimes($admin, ImmigrationApprovalRequestedNotification::class, 2);

    // A file still waiting shows Save, not Print.
    $this->actingAs($agent)->get(route('admin.client-sheets.edit', ['client_sheet' => $sheet->id, 'service' => 'ecc']))->assertOk()
        ->assertDontSee('This file is approved.')->assertSee('changed: true', false);
});
