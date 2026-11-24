<?php

use App\Models\Message;
use App\Models\User;
use App\Support\DocumentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

test('a changed password signs the account out of its other sessions', function () {
    $agent = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['inquiries']]);

    $this->actingAs($agent)->get(route('admin.inquiries.index'))->assertOk();

    // Changed somewhere else (e.g. by an admin): this session still holds the old password's hash.
    $this->travel(-5)->minutes();
    $agent->forceFill(['password' => bcrypt('a-new-password')])->save();
    $this->travelBack();

    $this->get(route('admin.inquiries.index'))->assertRedirect(route('admin.login'));
    $this->assertGuest();

    $this->get(route('admin.login'))
        ->assertSee('Your password was changed 5 minutes ago. Please sign in again with your new password.');
});

test('an admin who changes their own password stays signed in', function () {
    $admin = User::factory()->create(['role' => 'admin', 'email' => 'boss@example.com']);

    $this->actingAs($admin)->get(route('admin.agents.index'))->assertOk();

    $this->put(route('admin.agents.update', $admin), [
        'first_name' => 'Ana', 'last_name' => 'Cruz', 'email' => 'boss@example.com', 'phone' => '0917 000 1111',
        'password' => 'a-new-password',
    ])->assertSessionHasNoErrors();

    $this->get(route('admin.agents.index'))->assertOk();
    $this->assertAuthenticatedAs($admin);
});

test('a right password on a non-staff account gets the same message as a wrong one', function () {
    User::factory()->create(['role' => 'client', 'email' => 'client@example.com', 'password' => bcrypt('secret-123')]);

    foreach (['secret-123', 'wrong-one'] as $password) {
        $this->from(route('admin.login'))->post(route('admin.login'), ['email' => 'client@example.com', 'password' => $password])
            ->assertSessionHasErrors(['email' => 'Invalid administrator credentials.']);
    }

    $this->assertGuest();
});

test('chat files are kept private and open only through their signed link', function () {
    Storage::fake('public');
    Storage::fake(DocumentStorage::diskName());

    $this->postJson('/chat/send', [
        'guest_token' => 'gst_'.Str::random(24),
        'message' => 'My passport',
        'guest_name' => 'Juan Dela Cruz',
        'guest_email' => 'juan@gmail.com',
        'attachment' => UploadedFile::fake()->image('passport.jpg'),
    ])->assertOk();

    $stored = Message::query()->whereNotNull('attachment_url')->firstOrFail();
    $path = $stored->getRawOriginal('attachment_url');

    expect(Storage::disk('public')->allFiles())->toBe([])
        ->and(DocumentStorage::disk()->exists($path))->toBeTrue();

    $this->get($stored->attachment_url)->assertOk();
    $this->get(route('chat.attachment', basename($path)))->assertForbidden();
});
