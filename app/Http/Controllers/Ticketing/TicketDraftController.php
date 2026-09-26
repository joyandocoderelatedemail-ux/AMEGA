<?php

namespace App\Http\Controllers\Ticketing;

use App\Http\Controllers\Controller;
use App\Models\TicketDraft;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

/**
 * "Save as Pending" in the booking wizard: when a client's requirements are
 * not complete yet, the whole form is kept on the server and the wizard is
 * cleared for the next client. The ticket is continued later, exactly where
 * it was left, from the Ticket Directory.
 */
class TicketDraftController extends Controller
{
    /**
     * Save the wizard as pending, or update the pending ticket it came from.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'draft_id' => ['nullable', 'integer'],
            'step' => ['required', 'integer', 'min:1', 'max:30'],
            'payload' => ['required', 'array'],
        ]);

        $payload = $validated['payload'];

        // Reopening a pending ticket updates it; one this user cannot see (or
        // that was already issued) starts a fresh one.
        $draft = ! empty($validated['draft_id'])
            ? TicketDraft::find($validated['draft_id'])
            : null;

        $draft ??= new TicketDraft(['created_by' => Auth::id()]);

        $draft->fill([
            'client_name' => $this->clientName($payload),
            'summary' => $this->summary($payload),
            'step' => $validated['step'],
            'payload' => $payload,
        ])->save();

        ActivityLogger::log('Ticketing', 'SAVE_PENDING', "Saved a pending ticket for {$draft->client_name}");

        // Shown on the cleared wizard the browser opens next.
        session()->flash('success', "{$draft->client_name}'s ticket is saved as pending. Continue it any time from the Ticket Directory.");

        return response()->json([
            'id' => $draft->id,
            'saved_at' => $draft->updated_at->format('M j, g:i A'),
            'redirect' => route('ticketing.tickets.create'),
        ]);
    }

    /**
     * Discard a pending ticket.
     */
    public function destroy(TicketDraft $draft): RedirectResponse
    {
        $draft->delete();

        ActivityLogger::log('Ticketing', 'DISCARD_PENDING', "Discarded the pending ticket for {$draft->client_name}");

        return back()->with('success', 'Pending ticket discarded.');
    }

    /**
     * The booker's name, or the first traveller picked, for the directory list.
     *
     * @param  array<string, mixed>  $payload
     */
    private function clientName(array $payload): string
    {
        $name = trim((string) ($payload['contact_name'] ?? ''))
            ?: trim((string) Arr::get($payload, 'clients.0.name', ''));

        return $name !== '' ? mb_substr($name, 0, 255) : 'No client yet';
    }

    /**
     * "International · Manila → Tokyo · 3 pax" for the directory list.
     *
     * @param  array<string, mixed>  $payload
     */
    private function summary(array $payload): string
    {
        $route = collect([$payload['origin'] ?? null, $payload['destination'] ?? null])
            ->map(fn ($place) => trim((string) $place))
            ->filter()
            ->implode(' → ');

        $pax = (int) ($payload['total_passengers'] ?? 0);

        return mb_substr(collect([
            ucfirst((string) ($payload['travel_type'] ?? '')),
            $route,
            $pax > 0 ? $pax.' pax' : null,
        ])->filter()->implode(' · '), 0, 255);
    }
}
