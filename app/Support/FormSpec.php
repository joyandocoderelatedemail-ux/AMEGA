<?php

namespace App\Support;

/**
 * The validation and clean-up for a printed form's extra fields, described as sections of
 * [key, label, type, options?]. Types are text, date, select (options), checks (options,
 * several may apply) and yesno. Used by the SRRV PRA forms and the visa worksheets.
 */
class FormSpec
{
    /**
     * @param  array<string, list<array{0: string, 1: string, 2: string, 3?: array<string, string>}>>  $sections
     * @return array<string, list<string>>
     */
    public static function rules(array $sections, string $prefix = 'form'): array
    {
        $rules = [$prefix => ['nullable', 'array']];

        foreach ($sections as $fields) {
            foreach ($fields as $field) {
                [$key, , $type] = $field;
                $options = implode(',', array_keys($field[3] ?? []));
                match ($type) {
                    'date' => $rules["{$prefix}.{$key}"] = ['nullable', 'date'],
                    'select' => $rules["{$prefix}.{$key}"] = ['nullable', 'in:'.$options],
                    'yesno' => $rules["{$prefix}.{$key}"] = ['nullable', 'in:yes,no'],
                    'checks' => [$rules["{$prefix}.{$key}"] = ['nullable', 'array'], $rules["{$prefix}.{$key}.*"] = ['in:'.$options]],
                    default => $rules["{$prefix}.{$key}"] = ['nullable', 'string', 'max:255'],
                };
            }
        }

        return $rules;
    }

    /**
     * The validated fields worth keeping: blanks dropped.
     *
     * @return array<string, mixed>
     */
    public static function clean(?array $form): array
    {
        return collect($form ?? [])
            ->map(fn ($value) => is_array($value) ? array_values(array_filter($value)) : (is_string($value) ? trim($value) : $value))
            ->reject(fn ($value) => $value === null || $value === '' || $value === [])
            ->all();
    }
}
