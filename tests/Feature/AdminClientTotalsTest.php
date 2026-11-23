<?php

use App\Models\User;
use Illuminate\Support\Carbon;

test('the clients page counts every client and those added in the range, day by day', function () {
    Carbon::setTestNow('2026-11-18 10:00:00');
    $admin = User::factory()->create(['role' => 'admin']);
    $client = fn (string $at) => User::factory()->create(['role' => 'client'])->forceFill(['created_at' => $at])->save();

    $client('2026-11-01 09:00:00');
    $client('2026-11-17 09:00:00');
    $client('2026-11-18 08:00:00');
    $client('2026-11-18 09:00:00');

    $this->actingAs($admin)
        ->get(route('admin.users.index', ['from' => '2026-11-17', 'to' => '2026-11-18']))
        ->assertOk()
        ->assertViewHas('totalClients', 4)
        ->assertViewHas('addedCount', 3)
        ->assertViewHas('addedPerDay', fn ($days) => $days->all() === ['2026-11-18' => 2, '2026-11-17' => 1])
        ->assertSeeInOrder(['Wed, Nov 18, 2026', 'Tue, Nov 17, 2026']);
});
