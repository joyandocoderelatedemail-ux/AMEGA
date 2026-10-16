<?php

namespace App\Support;

use Closure;
use Illuminate\Validation\Rule;

/**
 * Provinces, cities/municipalities and ZIP codes of the Philippines, for a client's
 * residential address. The list lives in public/data/ph-locations.json (PSGC names via
 * the `psgc` npm package; ZIP codes via `zipcodes-ph`), which the address picker loads
 * in the browser and the server checks submissions against.
 */
class PhilippineLocations
{
    public const FILE = 'data/ph-locations.json';

    /** @var array{zipNames: array<string, string>, provinces: list<array{name: string, region: string, zips: list<string>, cities: list<array{name: string, zips: list<string>}>}>}|null */
    private static ?array $data = null;

    public static function data(): array
    {
        return self::$data ??= json_decode((string) file_get_contents(public_path(self::FILE)), true) ?: ['zipNames' => [], 'provinces' => []];
    }

    /** The file's URL, versioned so a new list is picked up straight away. */
    public static function url(): string
    {
        $path = public_path(self::FILE);

        return asset(self::FILE).(is_file($path) ? '?v='.filemtime($path) : '');
    }

    /** @return list<string> */
    public static function provinceNames(): array
    {
        return array_column(self::data()['provinces'], 'name');
    }

    /** @return list<string> */
    public static function cityNames(string $province): array
    {
        foreach (self::data()['provinces'] as $entry) {
            if ($entry['name'] === $province) {
                return array_column($entry['cities'], 'name');
            }
        }

        return [];
    }

    /**
     * Rules for the province, city and postal code fields. A new client needs the province
     * and city; on an older record they may stay empty. The city must be one of the
     * province's own.
     *
     * @return array<string, mixed>
     */
    public static function rules(bool $required = false): array
    {
        $presence = $required ? 'required' : 'nullable';

        return [
            'province' => [$presence, 'string', 'max:100', Rule::in(self::provinceNames())],
            'city' => [$presence, 'string', 'max:100', function (string $attribute, mixed $value, Closure $fail): void {
                $province = (string) request()->input('province');
                if (! in_array($value, self::cityNames($province), true)) {
                    $fail('Pick the city or municipality from the list for the province chosen.');
                }
            }],
            'postal_code' => ['nullable', 'digits:4'],
        ];
    }

    /**
     * The street typed in "Residential Address" is kept as the address line; the full
     * address (street, city, province and ZIP) is what the rest of the system prints.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function composeAddress(array $attributes): array
    {
        if (! array_key_exists('address', $attributes) || ! array_key_exists('province', $attributes)) {
            return $attributes;
        }

        $street = trim((string) $attributes['address']);
        $place = implode(', ', array_filter([$street, $attributes['city'] ?? null, $attributes['province'] ?? null]));
        $postal = trim((string) ($attributes['postal_code'] ?? ''));

        $attributes['address_line'] = $street !== '' ? $street : null;
        $attributes['address'] = trim($place.($postal !== '' ? ' '.$postal : '')) ?: null;
        if (filled($attributes['province'] ?? null)) {
            $attributes['country'] = 'Philippines';
        }

        return $attributes;
    }
}
