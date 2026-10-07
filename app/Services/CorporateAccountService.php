<?php

namespace App\Services;

use App\Models\CorporateAccount;
use Illuminate\Http\Request;
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
            $key('company_name') => [$required instanceof \Closure ? Rule::requiredIf($required) : ($required ? 'required' : 'nullable'), 'string', 'max:255'],
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
        $corporate = $request->input('account_category') === self::CATEGORY;
        $existing = $request->input('corporate_mode') === 'existing';

        return [
            'corporate_mode' => 'nullable|in:existing,new',
            'corporate_account_id' => [
                Rule::requiredIf($requireCompany && $corporate && $existing),
                'nullable',
                'exists:corporate_accounts,id',
            ],
        ] + self::companyRules('corporate', fn () => $corporate && ! $existing);
    }

    /**
     * The company a validated client form puts the client under, creating it
     * when new company details were entered. Null for a non-Corporate client.
     *
     * @param  array<string, mixed>  $validated
     */
    public static function resolve(array $validated): ?CorporateAccount
    {
        if (($validated['account_category'] ?? null) !== self::CATEGORY) {
            return null;
        }

        if (($validated['corporate_mode'] ?? 'existing') === 'new') {
            $company = CorporateAccount::create($validated['corporate']);
            ActivityLogger::log('Corporate', 'CREATE', "Registered corporate account '{$company->company_name}'");

            return $company;
        }

        return filled($validated['corporate_account_id'] ?? null)
            ? CorporateAccount::find($validated['corporate_account_id'])
            : null;
    }
}
