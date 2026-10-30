<?php

namespace App\Http\Controllers;

use App\Models\DeskDraft;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * "Save as Pending" at the Immigration, Visa Assistance and SRRV desks: keeps a half-filled form
 * without opening a file or sending anything for approval. Continue reopens the form with every
 * field as it was left; the form's own Save then opens the real file (and the pending form goes).
 * Uploaded scans are not kept.
 */
class DeskDraftController extends Controller
{
    /** The desk's dashboard, where its pending forms are listed. */
    public const DASHBOARDS = [
        'immigration' => 'admin.immigration.dashboard',
        'visa' => 'visa.dashboard',
        'srrv' => 'srrv.dashboard',
    ];

    /** Sent with the form but not part of what was typed. */
    private const NOT_KEPT = ['_token', '_method', 'then', 'acknowledged', '_pending_title', '_pending_url', '_desk_draft'];

    public function store(Request $request): RedirectResponse
    {
        $desk = $this->desk($request);
        $request->validate([
            '_pending_title' => ['required', 'string', 'max:255'],
            '_pending_url' => ['required', 'string', 'max:2000'],
        ]);

        // Only a form on this site is reopened.
        $url = (string) $request->input('_pending_url');
        if (parse_url($url, PHP_URL_HOST) !== $request->getHost()) {
            return redirect()->route(self::DASHBOARDS[$desk])->with('error', 'This form could not be saved as pending.');
        }

        $payload = Arr::except($request->input(), self::NOT_KEPT);
        $attributes = [
            'title' => $request->input('_pending_title'),
            'client_name' => $this->clientName($payload),
            'form_url' => $url,
            'payload' => $payload,
        ];

        $draft = DeskDraft::visibleTo($request->user(), $desk)->find($request->integer('_desk_draft'));
        $draft
            ? $draft->update($attributes)
            : $draft = DeskDraft::create($attributes + ['desk' => $desk, 'created_by' => $request->user()->id]);

        $who = $draft->client_name ?: 'no name yet';
        ActivityLogger::log('Pending', 'SAVE_PENDING', "Saved {$draft->title} ({$who}) as pending");

        $note = $request->allFiles() !== [] ? ' Uploaded files are not kept: attach them again when you continue.' : '';

        return redirect()->route(self::DASHBOARDS[$desk])
            ->with('success', "{$draft->title} saved as pending. Continue it from Pending on the dashboard.".$note);
    }

    /** Reopen the form with every field as it was left. */
    public function continue(Request $request, DeskDraft $deskDraft): RedirectResponse
    {
        $desk = $this->desk($request);
        abort_unless($deskDraft->desk === $desk && $deskDraft->isVisibleTo($request->user()), 404);

        $separator = str_contains($deskDraft->form_url, '?') ? '&' : '?';

        return redirect()->to($deskDraft->form_url.$separator.'draft='.$deskDraft->id)->withInput($deskDraft->payload ?? []);
    }

    public function destroy(Request $request, DeskDraft $deskDraft): RedirectResponse
    {
        $desk = $this->desk($request);
        abort_unless($deskDraft->desk === $desk && $deskDraft->isVisibleTo($request->user()), 404);

        $deskDraft->delete();
        ActivityLogger::log('Pending', 'DISCARD_PENDING', "Discarded pending {$deskDraft->title} ({$deskDraft->client_name})");

        return redirect()->route(self::DASHBOARDS[$desk])->with('success', 'Pending form discarded.');
    }

    /** The desk the route belongs to (set on each desk's routes). */
    private function desk(Request $request): string
    {
        return $request->route()->defaults['desk'];
    }

    /** The client's name, from whichever name fields the form has. */
    private function clientName(array $payload): ?string
    {
        foreach (['client_name', 'retiree_name'] as $key) {
            if (filled($payload[$key] ?? null) && is_string($payload[$key])) {
                return mb_substr(trim($payload[$key]), 0, 255);
            }
        }

        $name = collect([$payload['first_name'] ?? $payload['given_name'] ?? null, $payload['last_name'] ?? null])
            ->filter(fn ($part) => is_string($part) && trim($part) !== '')->implode(' ');

        return $name !== '' ? mb_substr($name, 0, 255) : null;
    }
}
