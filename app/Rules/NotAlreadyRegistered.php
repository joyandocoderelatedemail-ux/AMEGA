<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Stops the same person being registered twice.
 *
 * Staff register clients by hand at the admin and ticketing desks, so the
 * message names the account already on file: that is what they need to open
 * the existing record instead of typing a duplicate.
 */
class NotAlreadyRegistered implements DataAwareRule, ValidationRule
{
    public const EMAIL = 'email';

    public const PHONE = 'phone';

    public const NAME = 'name';

    /**
     * @var array<string, mixed>
     */
    protected array $data = [];

    public function __construct(private readonly string $field) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $existing = $this->existing((string) $value);

        if ($existing) {
            $fail($this->message($existing));
        }
    }

    /**
     * The account this value already belongs to, if any.
     */
    public function existing(string $value = ''): ?User
    {
        return match ($this->field) {
            self::EMAIL => $this->withEmail($value),
            self::PHONE => $this->withPhone($value),
            default => $this->withName(),
        };
    }

    public function message(User $existing): string
    {
        $who = $existing->name.' ('.$existing->email.')';

        return match ($this->field) {
            self::EMAIL => "This email address is already registered to {$who}.",
            self::PHONE => "This contact number is already registered to {$who}.",
            default => "A client with this name is already registered: {$who}.",
        };
    }

    private function withEmail(string $email): ?User
    {
        $email = mb_strtolower(trim($email));

        return $email === '' ? null : User::whereRaw('LOWER(email) = ?', [$email])->first();
    }

    /**
     * Numbers are compared by their digits, so "0917 123 4567", "0917-123-4567"
     * and "+63 917 123 4567" are the same number.
     */
    private function withPhone(string $phone): ?User
    {
        $digits = preg_replace('/\D/', '', $phone);

        if ($digits === '' || $digits === null) {
            return null;
        }

        $stored = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', ''), '.', '')";

        // A full mobile number is matched on its last ten digits, whatever the
        // country prefix; anything shorter has to match exactly.
        if (strlen($digits) >= 10) {
            return User::whereRaw("{$stored} LIKE ?", ['%'.substr($digits, -10)])->first();
        }

        return User::whereRaw("{$stored} = ?", [$digits])->first();
    }

    /**
     * The same first and last name is the same person, unless both records
     * carry a middle name or suffix and those differ.
     */
    private function withName(): ?User
    {
        $first = $this->lower($this->data['first_name'] ?? '');
        $last = $this->lower($this->data['last_name'] ?? '');

        if ($first === '' || $last === '') {
            return null;
        }

        $middle = $this->lower($this->data['middle_name'] ?? '');
        $suffix = $this->lower($this->data['suffix'] ?? '');
        $fullName = $this->lower(implode(' ', [$first, $middle, $last, $suffix]));

        return User::where(function (Builder $query) use ($first, $last, $fullName) {
            $query->where(function (Builder $named) use ($first, $last) {
                $named->whereRaw('LOWER(TRIM(first_name)) = ?', [$first])
                    ->whereRaw('LOWER(TRIM(last_name)) = ?', [$last]);
            })->orWhereRaw('LOWER(TRIM(name)) = ?', [$fullName]);
        })->get()->first(function (User $user) use ($middle, $suffix) {
            return ! $this->differs($middle, $user->middle_name) && ! $this->differs($suffix, $user->suffix);
        });
    }

    private function differs(string $entered, ?string $stored): bool
    {
        $stored = $this->lower($stored ?? '');

        return $entered !== '' && $stored !== '' && $entered !== $stored;
    }

    private function lower(mixed $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $value)));
    }
}
