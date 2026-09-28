<?php

namespace App\Http\Controllers\Ticketing;

use App\Http\Controllers\Controller;
use App\Models\Airline;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The airlines offered in the ticket wizard's fare search.
 *
 * The desk keeps this list itself, since it is the desk that knows each
 * airline's booking and agent-portal addresses. Airlines are switched off
 * rather than deleted, so tickets already booked on one keep their airline.
 */
class TicketAirlineController extends Controller
{
    public function index(): View
    {
        $airlines = Airline::query()->orderBy('sort_order')->orderBy('name')->get();

        return view('ticketing.airlines.index', compact('airlines'));
    }

    public function store(Request $request): RedirectResponse
    {
        $airline = Airline::create($this->validated($request) + ['is_active' => true]);

        ActivityLogger::log('Ticketing', 'CREATE', "Added airline '{$airline->name}' to the fare search");

        return redirect()->route('ticketing.airlines.index')->with('success', "{$airline->name} added.");
    }

    public function update(Request $request, Airline $airline): RedirectResponse
    {
        $airline->update($this->validated($request) + ['is_active' => $request->boolean('is_active')]);

        ActivityLogger::log('Ticketing', 'UPDATE', "Updated airline '{$airline->name}'");

        return redirect()->route('ticketing.airlines.index')->with('success', "{$airline->name} updated.");
    }

    /**
     * Only web addresses are accepted, so a saved link can never run script
     * when staff click it.
     *
     * @return array{name: string, code: ?string, booking_url: string, agent_portal_url: ?string, sort_order: int}
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'alpha_num', 'size:2'],
            'booking_url' => ['required', 'url:http,https', 'max:500'],
            'agent_portal_url' => ['nullable', 'url:http,https', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        return [
            'name' => $validated['name'],
            'code' => filled($validated['code'] ?? null) ? strtoupper($validated['code']) : null,
            'booking_url' => $validated['booking_url'],
            'agent_portal_url' => $validated['agent_portal_url'] ?? null,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ];
    }
}
