<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CorporateAccount;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\CorporateAccountService;
use Illuminate\Http\Request;

class AdminCorporateAccountController extends Controller
{
    public function index(Request $request)
    {
        $companies = CorporateAccount::query()
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
            ->orderBy('company_name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.corporates.index', compact('companies'));
    }

    public function create()
    {
        return view('admin.corporates.create');
    }

    public function store(Request $request)
    {
        $company = CorporateAccount::create($request->validate(CorporateAccountService::companyRules()));

        ActivityLogger::log('Corporate', 'CREATE', "Registered corporate account '{$company->company_name}'");

        return redirect()->route('admin.corporates.show', $company)->with('success', 'Corporate account created. You can now add members to it.');
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
        $corporate->update($request->validate(CorporateAccountService::companyRules()));

        ActivityLogger::log('Corporate', 'UPDATE', "Updated corporate account '{$corporate->company_name}'");

        return redirect()->route('admin.corporates.show', $corporate)->with('success', 'Corporate details updated.');
    }

    public function destroy(CorporateAccount $corporate)
    {
        $name = $corporate->company_name;
        $corporate->delete();

        ActivityLogger::log('Corporate', 'DELETE', "Deleted corporate account '{$name}'; its members were kept as clients");

        return redirect()->route('admin.corporates.index')->with('success', "{$name} was deleted. Its members are still registered clients.");
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
            'account_category' => CorporateAccountService::CATEGORY,
        ]);

        ActivityLogger::log('Corporate', 'UPDATE', "Added '{$client->name}' to corporate account '{$corporate->company_name}'");

        return redirect()->route('admin.corporates.show', $corporate)->with('success', "{$client->name} was added to {$corporate->company_name}.");
    }

    /**
     * Take a client out of this company. They stay registered.
     */
    public function removeMember(CorporateAccount $corporate, User $user)
    {
        abort_unless($user->corporate_account_id === $corporate->id, 404);

        $user->update(['corporate_account_id' => null]);

        ActivityLogger::log('Corporate', 'UPDATE', "Removed '{$user->name}' from corporate account '{$corporate->company_name}'");

        return redirect()->route('admin.corporates.show', $corporate)->with('success', "{$user->name} was removed from {$corporate->company_name}.");
    }
}
