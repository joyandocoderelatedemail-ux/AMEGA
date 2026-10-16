<?php

namespace App\Support;

/**
 * The counter's per-country steps for the Non-Immigrant flow (config/visa_playbooks.php).
 * A country with no playbook still runs the generic stages.
 */
class VisaPlaybook
{
    /**
     * @return array{portal: ?string, fees: list<string>, tasks: array<string, array<string, string>>, notes: array<string, list<string>>}|null
     */
    public static function for(?string $country): ?array
    {
        $playbook = $country ? (config('visa_playbooks') ?? [])[$country] ?? null : null;

        return is_array($playbook) ? $playbook : null;
    }

    /**
     * The tasks staff tick off in a stage, as id => label.
     *
     * @return array<string, string>
     */
    public static function tasks(?string $country, string $stage): array
    {
        return self::for($country)['tasks'][$stage] ?? [];
    }

    /**
     * @return list<string>
     */
    public static function notes(?string $country, string $stage): array
    {
        return self::for($country)['notes'][$stage] ?? [];
    }
}
