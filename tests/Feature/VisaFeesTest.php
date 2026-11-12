<?php

use App\Models\User;
use App\Models\VisaPricingTier;

function visaFee(array $attributes = []): VisaPricingTier
{
    return VisaPricingTier::create(array_merge([
        'service_type' => 'visit_visa', 'label' => 'Visit Visa — Japan', 'country' => 'Japan',
        'amount' => 5600, 'service_fee' => 3500, 'visa_fee' => 2100, 'needs_review' => false, 'is_active' => true,
    ], $attributes));
}

test('the fees left the dashboard for their own page, opened from a button beside the heading', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    visaFee();

    $this->actingAs($officer)->get(route('visa.dashboard'))->assertOk()
        ->assertDontSee('Published Fees')
        ->assertSee('href="'.route('visa.fees.index').'"', false);

    $this->actingAs($officer)->get(route('visa.fees.index'))->assertOk()
        ->assertSee('Visit Visa — Japan')
        ->assertSee('₱5,600')
        ->assertSee('Read only.')
        ->assertDontSee('name="label"', false);
});

test('a counter officer without Manage prices cannot change a fee', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $fee = visaFee();
    $count = VisaPricingTier::count();

    $this->actingAs($officer)->put(route('visa.fees.update', $fee), ['label' => 'Cheap', 'amount' => 1])->assertForbidden();
    $this->actingAs($officer)->post(route('visa.fees.store'), ['service_type' => 'e_visa', 'label' => 'X', 'amount' => 1])->assertForbidden();

    expect($fee->fresh()->label)->toBe('Visit Visa — Japan')->and(VisaPricingTier::count())->toBe($count);
});

test('an admin edits a fee on the page, the price being the service fee plus the visa fee', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $fee = visaFee();

    $this->actingAs($admin)->get(route('visa.fees.index'))->assertOk()->assertSee('action="'.route('visa.fees.update', $fee).'"', false)->assertSee('Add a fee');

    $this->actingAs($admin)->put(route('visa.fees.update', $fee), [
        'label' => 'Visit Visa — Japan', 'country' => 'Japan', 'service_fee' => 4000, 'visa_fee' => 2100,
        'amount' => 1, 'is_active' => '1', 'confirmed' => '1',
    ])->assertRedirect(route('visa.fees.index'));

    expect((float) $fee->fresh()->amount)->toBe(6100.0)->and($fee->fresh()->needs_review)->toBeFalse();

    // Unticking Confirmed takes it off the counter's price list.
    $this->actingAs($admin)->put(route('visa.fees.update', $fee), ['label' => 'Visit Visa — Japan', 'service_fee' => 4000, 'visa_fee' => 2100, 'is_active' => '1']);
    expect(VisaPricingTier::published()->whereKey($fee->id)->exists())->toBeFalse();

    $this->actingAs($admin)->post(route('visa.fees.store'), [
        'service_type' => 'passporting', 'label' => 'Passport — New', 'amount' => 1500, 'is_active' => '1', 'confirmed' => '1',
    ])->assertRedirect(route('visa.fees.index'));
    expect((float) VisaPricingTier::where('label', 'Passport — New')->value('amount'))->toBe(1500.0);

    $this->actingAs($admin)->delete(route('visa.fees.destroy', $fee))->assertRedirect(route('visa.fees.index'));
    expect(VisaPricingTier::find($fee->id))->toBeNull();
});
