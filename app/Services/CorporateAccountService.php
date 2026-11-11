<?php

namespace App\Services;

use App\Models\CorporateAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * Corporate accounts: the company details, and which client belongs to which.
 *
 * Shared by the company pages and the client form, so a company can be
 * registered on its own or while its first member is being added.
 */
class CorporateAccountService
{
    public const CATEGORY = 'Corporate';

    public const GROUP_CATEGORY = 'Group';

    /** The kind of account a client of this category travels under, or null (an Individual). */
    public static function kindFor(?string $category): ?string
    {
        $kind = array_search($category, CorporateAccount::CATEGORIES, true);

        return $kind === false ? null : $kind;
    }

    /**
     * Rules for the company fields.
     *
     * @param  string  $prefix  '' on the company form, 'corporate' when nested in the client form.
     * @param  bool  $required  Whether the company name must be given.
     * @return array<string, mixed>
     */
    public static function companyRules(string $prefix = '', bool|\Closure $required = true): array
    {
        $key = fn (string $field) => $prefix === '' ? $field : "{$prefix}.{$field}";

        return [
            // The client form always sends the hidden company fields; for anyone not joining a
            // new company the name comes in empty, which must pass rather than fail "string".
            $key('company_name') => $required instanceof \Closure
                ? [Rule::requiredIf($required), 'nullable', 'string', 'max:255']
                : [$required ? 'required' : 'nullable', 'string', 'max:255'],
            $key('registration_number') => 'nullable|string|max:100',
            $key('tin') => 'nullable|string|max:50',
            $key('industry') => 'nullable|string|max:150',
            $key('address') => 'nullable|string|max:500',
            $key('contact_person') => 'nullable|string|max:255',
            $key('contact_position') => 'nullable|string|max:150',
            $key('contact_email') => 'nullable|email|max:255',
            $key('contact_phone') => 'nullable|string|max:50',
            $key('notes') => 'nullable|string|max:2000',
        ];
    }

    /**
     * Rules for the "which company" part of the client form. Only a Corporate
     * client needs a company; the choice is ignored for any other category.
     * Editing passes $requireCompany false, so a Corporate client from before
     * companies existed can still be saved without being assigned one.
     *
     * @return array<string, mixed>
     */
    public static function memberRules(Request $request, bool $requireCompany = true): array
    {
        // A Corporate client goes under a company, a Group client under a group: the same choice.
        $kind = self::kindFor($request->input('account_category'));
        $corporate = $kind !== null;
        $existing = $request->input('corporate_mode') === 'existing';

        return [
            'corporate_mode' => 'nullable|in:existing,new',
            'corporate_account_id' => [
                Rule::requiredIf($requireCompany && $corporate && $existing),
                'nullable',
                // A company for a Corporate client, a group for a Group client, never the other.
                Rule::exists('corporate_accounts', 'id')->where('kind', $kind ?? CorporateAccount::KIND_CORPORATE),
            ],
        ] + self::companyRules('corporate', fn () => $requireCompany && $corporate && ! $existing);
    }

    /**
     * What the client form says when the company or group part is missing, in the words of the
     * category chosen.
     *
     * @return array<string, string>
     */
    public static function memberMessages(Request $request): array
    {
        $noun = self::kindFor($request->input('account_category')) === CorporateAccount::KIND_GROUP ? 'group' : 'company';

        return [
            'corporate.company_name.required' => "Enter the name of the new {$noun}.",
            'corporate_account_id.required' => "Choose the {$noun} this client belongs to, or register a new one.",
            'corporate_account_id.exists' => "Choose a {$noun} from the list.",
        ];
    }

    /**
     * The company a validated client form puts the client under, creating it
     * (or group) when new details were entered. Null for an Individual client.
     *
     * @param  array<string, mixed>  $validated
     */
    public static function resolve(array $validated): ?CorporateAccount
    {
        $kind = self::kindFor($validated['account_category'] ?? null);
        if ($kind === null) {
            return null;
        }

        if (($validated['corporate_mode'] ?? 'existing') === 'new') {
            // Editing an older client may leave the new company or group unnamed: then there is none.
            return filled($validated['corporate']['company_name'] ?? null) ? self::create($validated['corporate'], $kind) : null;
        }

        return filled($validated['corporate_account_id'] ?? null)
            ? CorporateAccount::ofKind($kind)->find($validated['corporate_account_id'])
            : null;
    }

    /**
     * Register a company or a group. A group keeps only its name, leader, contact details and notes.
     *
     * @param  array<string, mixed>  $details
     */
    public static function create(array $details, string $kind = CorporateAccount::KIND_CORPORATE): CorporateAccount
    {
        $group = $kind === CorporateAccount::KIND_GROUP;

        $account = new CorporateAccount($group ? Arr::only($details, CorporateAccount::GROUP_FIELDS) : $details);
        $account->kind = $group ? CorporateAccount::KIND_GROUP : CorporateAccount::KIND_CORPORATE;
        $account->save();

        ActivityLogger::log('Corporate', 'CREATE', $group
            ? "Registered group '{$account->company_name}'"
            : "Registered corporate account '{$account->company_name}'");

        return $account;
    }
}
