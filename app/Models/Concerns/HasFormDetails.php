<?php

namespace App\Models\Concerns;

use Illuminate\Support\Carbon;

/**
 * Reads a printed form's extra details from the model's `form_details` JSON column.
 */
trait HasFormDetails
{
    public function detail(string $key): mixed
    {
        $value = ($this->form_details ?? [])[$key] ?? null;

        return $value === '' || $value === [] ? null : $value;
    }

    public function dateDetail(string $key): ?Carbon
    {
        $value = $this->detail($key);

        return is_string($value) && $value !== '' ? Carbon::parse($value) : null;
    }

    /** A checkbox group's picked options. */
    public function listDetail(string $key): array
    {
        return (array) ($this->detail($key) ?? []);
    }
}
