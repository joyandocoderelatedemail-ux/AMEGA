<?php

namespace App\Http\Controllers\Ticketing;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\ClientProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Finding or registering the client at the ticketing desk, before a booking.
 *
 * Ticketing officers cannot reach the admin client directory, so the search
 * and the registration form live inside the ticketing portal.
 */
class TicketClientController extends Controller
{
    /** How many matches the picker shows at once. */
    private const RESULT_LIMIT = 8;

    /**
     * Search registered clients by name, email, phone, passport or ID number.
     */
    public function search(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('q'));

        if (mb_strlen($term) < 2) {
            return response()->json(['clients' => []]);
        }

        $clients = User::clientSearch($term)
            ->orderBy('name')
            ->limit(self::RESULT_LIMIT)
            ->get();

        return response()->json([
            'clients' => $clients->map(fn (User $client) => ClientProfileService::ticketProfile($client))->values(),
        ]);
    }

    /**
     * The registration form, for a client who is not on file yet.
     */
    public function create(Request $request): View
    {
        return view('ticketing.clients.create', [
            'prefill' => $this->namePrefill((string) $request->input('name')),
        ]);
    }

    /**
     * Start the form from what was typed into the search, when it reads as a
     * name rather than an email, phone or passport number.
     *
     * @return array{first_name?: string, last_name?: string}
     */
    private function namePrefill(string $typed): array
    {
        $typed = trim(preg_replace('/\s+/', ' ', $typed));

        if ($typed === '' || preg_match('/[@\d]/', $typed)) {
            return [];
        }

        $parts = explode(' ', $typed);
        $lastName = count($parts) > 1 ? array_pop($parts) : '';

        return ['first_name' => implode(' ', $parts), 'last_name' => $lastName];
    }

    /**
     * Register the client, then return to a new booking with them selected.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(ClientProfileService::registrationRules());

        $client = ClientProfileService::register($validated, $request);

        ActivityLogger::log('Ticketing', 'REGISTER_CLIENT', "Registered client '{$client->name}' ({$client->email}) at the ticketing desk");

        return redirect()->route('ticketing.tickets.create', ['client' => $client->id])
            ->with('success', "{$client->name} is registered and selected for this booking.");
    }
}
