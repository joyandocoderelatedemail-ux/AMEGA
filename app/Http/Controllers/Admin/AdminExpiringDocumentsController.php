<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Services\ExpiryReminderSender;
use App\Support\ClientExpiries;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Reports & Analytics > Expiring Documents: every client visa, passport and ACR I-Card with an expiry
 * date, soonest first, with Remind. The daily job reminds each one a month ahead by itself.
 */
class AdminExpiringDocumentsController extends Controller
{
    /** How far ahead the page looks, in days; "all" shows every date on file. */
    public const WINDOWS = ['30' => 'Next 30 days', '60' => 'Next 60 days', '90' => 'Next 90 days', '180' => 'Next 6 months', 'all' => 'All dates on file'];

    public function index(Request $request): View
    {
        $document = array_key_exists((string) $request->query('document'), ClientExpiries::DOCUMENTS) ? (string) $request->query('document') : '';
        $window = array_key_exists((string) $request->query('within'), self::WINDOWS) ? (string) $request->query('within') : '90';
        $expired = $request->query('expired', '1') === '1';
        $term = mb_strtolower(trim((string) $request->query('search')));

        $all = ClientExpiries::all();

        $rows = $all
            ->when($document !== '', fn ($rows) => $rows->where('document', $document))
            ->filter(fn (array $row) => $row['days'] < 0
                ? $expired
                : ($window === 'all' || $row['days'] <= (int) $window))
            ->when($term !== '', fn ($rows) => $rows->filter(fn (array $row) => str_contains(mb_strtolower(implode(' ', [$row['name'], $row['email'], $row['phone'], $row['number']])), $term)))
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginated = new LengthAwarePaginator($rows->forPage($page, 50)->values(), $rows->count(), 50, $page, ['path' => $request->url(), 'query' => $request->query()]);

        return view('admin.expiring-documents.index', [
            'rows' => $paginated,
            'summary' => [
                'expired' => $all->where('days', '<', 0)->count(),
                'month' => $all->filter(fn (array $row) => $row['days'] >= 0 && $row['days'] <= 30)->count(),
                'quarter' => $all->filter(fn (array $row) => $row['days'] >= 0 && $row['days'] <= 90)->count(),
                'unreachable' => $all->filter(fn (array $row) => $row['days'] <= 30 && ! $row['email'] && ! $row['phone'])->count(),
            ],
            'filters' => [
                'document' => $document,
                'window' => $window,
                'expired' => $expired,
                'search' => (string) $request->query('search'),
            ],
        ]);
    }

    /** Remind the client about one document now. */
    public function remind(Request $request): RedirectResponse
    {
        $key = (string) $request->validate(['key' => ['required', 'string', 'max:60']])['key'];
        $row = ClientExpiries::all()->firstWhere('key', $key);

        if ($row === null) {
            return back()->with('error', 'That document is no longer on file.');
        }

        $sent = ExpiryReminderSender::send($row, $request->user());

        if ($sent === null) {
            return back()->with('error', "{$row['name']} has no email address on file to remind. Call them instead".($row['phone'] ? " on {$row['phone']}" : '').'.');
        }

        $verb = $row['days'] < 0 ? 'expired' : 'expires';
        ActivityLogger::log('Clients', 'REMIND', "Reminded {$row['name']} that their {$row['label']} {$verb} on {$row['date']->format('M j, Y')} (by {$sent->channel})");

        return back()->with('success', "{$row['name']} was reminded about their {$row['label']}.");
    }
}
