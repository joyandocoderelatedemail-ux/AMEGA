<?php

use App\Models\ImmigrationClient;
use App\Models\ImmigrationPetition;
use App\Models\User;
use App\Support\AdminNotifications;
use App\Support\ImmigrationCases;
use Illuminate\Support\Facades\DB;

/**
 * Deleting a client folder removes its cases. A database that does not cascade (the live host's MyISAM) used to
 * leave them behind as "Unknown client" files waiting for approval that nobody could open.
 */
function orphanCase(array $attributes = []): ImmigrationPetition
{
    $folder = ImmigrationClient::forceCreate(['last_name' => 'Gone', 'given_name' => 'Client']);

    return ImmigrationPetition::withoutGlobalScopes()->forceCreate($attributes + [
        'immigration_client_id' => $folder->id, 'type' => 'ecc', 'details' => [], 'approval_status' => 'pending', 'approval_requested_at' => now(),
    ]);
}

/**
 * The state a database that does not cascade (MyISAM) leaves after a folder is deleted: the case still exists and
 * points at a folder that does not. SQLite enforces the foreign key, so the check is deferred past the test.
 */
function leaveWithoutFolder(ImmigrationPetition $case): void
{
    DB::statement('PRAGMA defer_foreign_keys = ON');
    DB::table('immigration_petitions')->where('id', $case->id)->update(['immigration_client_id' => 999999]);
    expect(ImmigrationPetition::withoutGlobalScopes()->whereKey($case->id)->exists())->toBeTrue();
}

test('deleting a folder deletes its cases, extensions and documents with it', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $case = orphanCase();
    $folder = ImmigrationClient::withoutGlobalScopes()->findOrFail($case->immigration_client_id);

    $this->actingAs($admin)->delete(route('admin.client-sheets.destroy', $folder))->assertRedirect();

    expect(ImmigrationClient::withoutGlobalScopes()->whereKey($folder->id)->exists())->toBeFalse()
        ->and(ImmigrationPetition::withoutGlobalScopes()->whereKey($case->id)->exists())->toBeFalse();
});

test('a case left without a folder is not listed, counted or waiting on anyone', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $orphan = orphanCase();
    leaveWithoutFolder($orphan);

    $this->actingAs($admin)->get(route('admin.client-sheets.applications', ['service' => 'ecc']))->assertOk()
        ->assertDontSee('Unknown client')->assertDontSee('Needs action')->assertSee('No ECC transactions yet.');

    expect(ImmigrationCases::activeCounts()['ecc'])->toBe(['open' => 0, 'waiting' => 0])
        ->and(ImmigrationCases::search(null, 'all', '', 50))->toHaveCount(0)
        ->and(AdminNotifications::for($admin)['immigration_approvals'])->toBe(0);
});

test('the clean-orphans command lists first, asks, and deletes only cases with no folder', function () {
    $kept = orphanCase();
    $orphan = orphanCase();
    leaveWithoutFolder($orphan);

    // A dry run shows the count and touches nothing.
    $this->artisan('immigration:clean-orphans', ['--dry-run' => true])->expectsOutputToContain('Dry run')->assertSuccessful();
    expect(ImmigrationPetition::withoutGlobalScopes()->count())->toBe(2);

    // Answering no deletes nothing.
    $this->artisan('immigration:clean-orphans')->expectsConfirmation('Delete these 1 records for good? Take a database backup first.', 'no')->assertSuccessful();
    expect(ImmigrationPetition::withoutGlobalScopes()->count())->toBe(2);

    // Answering yes deletes the orphan and keeps the case whose folder exists.
    $this->artisan('immigration:clean-orphans')->expectsConfirmation('Delete these 1 records for good? Take a database backup first.', 'yes')->expectsOutputToContain('Deleted 1 records')->assertSuccessful();
    expect(ImmigrationPetition::withoutGlobalScopes()->pluck('id')->all())->toBe([$kept->id]);

    // Nothing left to clean.
    $this->artisan('immigration:clean-orphans')->expectsOutputToContain('Nothing to clean')->assertSuccessful();
});
