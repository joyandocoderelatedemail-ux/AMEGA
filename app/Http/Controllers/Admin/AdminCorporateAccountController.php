<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CorporateAccount;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\CorporateAccountService;
use App\Support\DateRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class AdminCorporateAccountController extends Controller
{
    public function index(Request $request)
    {
        $kind = $this->kind($request);

        $companies = CorporateAccount::query()
            ->ofKind($kind)
            ->withCount('members')
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = $request->input('search');
                $query->where(function ($q) use ($term) {
                    $q->where('company_name', 'like', "%{$term}%")
                        ->orWhere('contact_person', 'like', "%{$term}%")
                        ->orWhere('registration_number', 'like', "%{$term}%")
                        ->orWhere('tin', 'like', "%{$term}%");
                });
            })
            ->when(true, fn ($query) => DateRange::fromRequest($request)->apply($query))
            ->orderBy('company_name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.corporates.index', [
            'companies' => $companies,
            'kind' => $kind,
            'counts' => [
                CorporateAccount::KIND_CORPORATE => CorporateAccount::ofKind(CorporateAccount::KIND_CORPORATE)->count(),
                CorporateAccount::KIND_GROUP => CorporateAccount::ofKind(CorporateAccount::KIND_GROUP)->count(),
            ],
        ]);
    }

    public function create(Request $request)
    {
        return view('admin.corporates.create', ['kind' => $this->kind($request)]);
    }

    public function store(Request $request)
    {
        $kind = $this->kind($request);
        $company = CorporateAccountService::create($request->validate(CorporateAccountService::companyRules()), $kind);

        return redirect()->route('admin.corporates.show', $company)->with('success', $company->isGroup()
            ? 'Group created. You can now add its members.'
            : 'Corporate account created. You can now add members to it.');
    }

    /** Companies or groups: what the request is about (?kind=group, or the form's hidden field). */
    private function kind(Request $request): string
    {
        return $request->input('kind') === CorporateAccount::KIND_GROUP ? CorporateAccount::KIND_GROUP : CorporateAccount::KIND_CORPORATE;
    }

    public function show(Request $request, CorporateAccount $corporate)
    {
        $members = $corporate->members()->orderBy('name')->get();

        // Existing clients to assign: matched by what staff type, never already in this company.
        $term = trim((string) $request->input('find'));
        $candidates = $term === '' ? collect() : User::where('role', 'client')
            ->where(fn ($q) => $q->whereNull('corporate_account_id')->orWhere('corporate_account_id', '!=', $corporate->id))
            ->clientSearch($term)
            ->with('corporateAccount')
            ->orderBy('name')
            ->limit(10)
            ->get();

        return view('admin.corporates.show', compact('corporate', 'members', 'candidates', 'term'));
    }

    public function edit(CorporateAccount $corporate)
    {
        return view('admin.corporates.edit', compact('corporate'));
    }

    public function update(Request $request, CorporateAccount $corporate)
    {
        $details = $request->validate(CorporateAccountService::companyRules());
        $corporate->update($corporate->isGroup() ? Arr::only($details, CorporateAccount::GROUP_FIELDS) : $details);

        ActivityLogger::log('Corporate', 'UPDATE', "Updated {$corporate->noun()} '{$corporate->company_name}'");

        return redirect()->route('admin.corporates.show', $corporate)->with('success', $corporate->isGroup() ? 'Group details updated.' : 'Corporate details updated.');
    }

    /**
     * The company's credit account (admins only): whether it may charge tickets and pay later, its limit,
     * its net days, and the late interest it agreed to. The change is logged with the before and after.
     */
    public function credit(Request $request, CorporateAccount $corporate)
    {
        // A group pays as it books: no credit account, no terms, no interest.
        abort_if($corporate->isGroup(), 404);

        $validated = $request->validate([
            'credit_enabled' => ['nullable', 'boolean'],
            'credit_limit' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'credit_terms_days' => ['required', 'integer', 'min:1', 'max:365'],
            'credit_interest_rate' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'credit_grace_days' => ['nullable', 'integer', 'min:0', 'max:90'],
        ], [
            'credit_terms_days.required' => 'Enter the payment terms in days (e.g. 30).',
            'credit_interest_rate.max' => 'The late interest cannot be more than 10% per month.',
        ]);

        $before = $corporate->only(['credit_enabled', 'credit_limit', 'credit_terms_days', 'credit_interest_rate', 'credit_grace_days']);

        $corporate->forceFill([
            'credit_enabled' => $request->boolean('credit_enabled'),
            'credit_limit' => filled($validated['credit_limit'] ?? null) ? round((float) $validated['credit_limit'], 2) : null,
            'credit_terms_days' => (int) $validated['credit_terms_days'],
            'credit_interest_rate' => filled($validated['credit_interest_rate'] ?? null) ? round((float) $validated['credit_interest_rate'], 2) : null,
            'credit_grace_days' => (int) ($validated['credit_grace_days'] ?? 0),
        ])->save();

        ActivityLogger::log('Corporate', 'CREDIT', "Changed the credit account of '{$corporate->company_name}'", [
            'corporate_id' => $corporate->id,
            'before' => $before,
            'after' => $corporate->only(array_keys($before)),
        ]);

        return redirect()->route('admin.corporates.show', $corporate)->with('success', $corporate->credit_enabled
            ? "Credit account saved: Net {$corporate->credit_terms_days} days".($corporate->credit_limit !== null ? ', limit ₱'.number_format((float) $corporate->credit_limit, 2) : ', no limit').'.'
            : 'Credit account turned off. What the company already owes stays on its account.');
    }

    public function destroy(CorporateAccount $corporate)
    {
        $name = $corporate->company_name;
        $group = $corporate->isGroup();
        $noun = $group ? 'group' : 'corporate account';
        $corporate->delete();

        ActivityLogger::log('Corporate', 'DELETE', "Deleted {$noun} '{$name}'; its members were kept as clients");

        return redirect()->route('admin.corporates.index', $group ? ['kind' => CorporateAccount::KIND_GROUP] : [])
            ->with('success', "{$name} was deleted. Its members are still registered clients.");
    }

    /**
     * Put an existing client under this company.
     */
    public function assignMember(Request $request, CorporateAccount $corporate)
    {
        $validated = $request->validate(['user_id' => 'required|integer|exists:users,id']);

        $client = User::where('role', 'client')->findOrFail($validated['user_id']);
        $client->update([
            'corporate_account_id' => $corporate->id,
            'account_category' => $corporate->category(),
        ]);

        ActivityLogger::log('Corporate', 'UPDATE', "Added '{$client->name}' to ".($corporate->isGroup() ? 'group' : 'corporate account')." '{$corporate->company_name}'");

        return redirect()->route('admin.corporates.show', $corporate)->with('success', "{$client->name} was added to {$corporate->company_name}.");
    }

    /**
     * Take a client out of this company. They stay registered.
     */
    public function removeMember(CorporateAccount $corporate, User $user)
    {
        abort_unless($user->corporate_account_id === $corporate->id, 404);

        $user->update(['corporate_account_id' => null]);

        ActivityLogger::log('Corporate', 'UPDATE', "Removed '{$user->name}' from ".($corporate->isGroup() ? 'group' : 'corporate account')." '{$corporate->company_name}'");

        return redirect()->route('admin.corporates.show', $corporate)->with('success', "{$user->name} was removed from {$corporate->company_name}.");
    }

    /**
     * Registered clients matching a name, email or phone, to pick as a group's leader.
     */
    public function leaderSearch(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('q'));

        if (mb_strlen($term) < 2) {
            return response()->json(['clients' => []]);
        }

        $clients = User::clientSearch($term)->orderBy('name')->limit(10)->get(['id', 'name', 'email', 'phone']);

        return response()->json([
            'clients' => $clients->map(fn (User $client) => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'phone' => $client->phone,
            ])->values(),
        ]);
    }
}
