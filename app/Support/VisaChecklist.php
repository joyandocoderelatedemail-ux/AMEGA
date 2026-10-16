<?php

namespace App\Support;

/**
 * The per-country document checklists for the Non-Immigrant flow
 * (config/visa_checklists.php). A destination with no checklist falls back to
 * the passport copy alone.
 */
class VisaChecklist
{
    /**
     * The checklist variants a destination offers, as key => definition.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function variants(?string $country): array
    {
        return $country ? (config('visa_checklists') ?? [])[$country] ?? [] : [];
    }

    /**
     * One variant, only if it belongs to the destination.
     *
     * @return array<string, mixed>|null
     */
    public static function find(?string $country, ?string $key): ?array
    {
        return $key ? self::variants($country)[$key] ?? null : null;
    }

    /**
     * A variant by its key alone, with the destination it belongs to.
     *
     * @return array{0: string, 1: array<string, mixed>}|null
     */
    public static function findByKey(string $key): ?array
    {
        foreach (config('visa_checklists') ?? [] as $country => $set) {
            if (isset($set[$key])) {
                return [$country, $set[$key]];
            }
        }

        return null;
    }

    /**
     * The printed sheet's title, as on the counter's own checklists.
     *
     * @param  array<string, mixed>  $variant
     */
    public static function title(string $country, array $variant): string
    {
        $short = ['Schengen (Europe)' => 'Schengen', 'United Kingdom' => 'UK', 'USA' => 'US'][$country] ?? $country;
        $name = str_contains($variant['label'], ':') ? str_replace(':', '', $variant['label']) : $short.' '.$variant['label'];

        return mb_strtoupper($name.' Visa Checklist');
    }

    /**
     * Every row id in a section, including the items inside groups.
     *
     * @param  array<string, mixed>  $section
     * @return list<string>
     */
    public static function rowIds(array $section): array
    {
        $ids = [];

        foreach ($section['rows'] as $row) {
            $ids[] = $row['id'];

            foreach ($row['items'] ?? [] as $item) {
                $ids[] = $item['id'];
            }
        }

        return $ids;
    }

    /**
     * Every row id an owner ("applicant" or "sponsor") can tick on a variant.
     *
     * @param  array<string, mixed>  $variant
     * @return list<string>
     */
    public static function idsFor(array $variant, string $owner): array
    {
        $ids = [];

        foreach ($variant['sections'] as $section) {
            if ($section['owner'] === $owner) {
                array_push($ids, ...self::rowIds($section));
            }
        }

        return $ids;
    }

    /**
     * The variants for the intake form: what to offer for a destination, and the
     * applicant's rows to preview before the file exists.
     *
     * @return array{variants: array<string, list<array{key: string, label: string, purpose: string}>>, preview: array<string, list<array{label: string, indent: bool}>>}
     */
    public static function forIntake(): array
    {
        $variants = [];
        $preview = [];

        foreach (config('visa_checklists') ?? [] as $country => $set) {
            foreach ($set as $key => $variant) {
                $variants[$country][] = ['key' => $key, 'label' => $variant['label'], 'purpose' => $variant['purpose']];

                $rows = [];

                foreach ($variant['sections'] as $section) {
                    if ($section['owner'] !== 'applicant' || $section['key'] === 'minor') {
                        continue;
                    }

                    foreach ($section['rows'] as $row) {
                        $rows[] = ['label' => $row['label'].(isset($row['items']) ? ' (any of the below)' : ''), 'indent' => false];

                        foreach ($row['items'] ?? [] as $item) {
                            $rows[] = ['label' => $item['label'], 'indent' => true];
                        }
                    }
                }

                $preview[$key] = $rows;
            }
        }

        return ['variants' => $variants, 'preview' => $preview];
    }
}
