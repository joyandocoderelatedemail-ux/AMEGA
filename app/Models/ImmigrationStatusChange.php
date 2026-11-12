<?php

namespace App\Models;

use App\Support\CaseProgress;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One status change on an Immigration case and the remark given for it. A case is named by its kind
 * (as ImmigrationCases names it) and id. Rows are only ever added, never edited.
 */
class ImmigrationStatusChange extends Model
{
    protected $fillable = [
        'case_kind',
        'case_id',
        'from_status',
        'to_status',
        'remark',
        'released_on',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'case_id' => 'integer',
            'released_on' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param  Builder<self>  $query */
    public function scopeForCase(Builder $query, string $kind, int $id): void
    {
        $query->where('case_kind', $kind)->where('case_id', $id)->latest('id');
    }

    public function fromLabel(): ?string
    {
        return $this->from_status ? self::label($this->from_status) : null;
    }

    public function toLabel(): string
    {
        return self::label($this->to_status);
    }

    private static function label(string $status): string
    {
        return $status === CaseProgress::RELEASED ? 'Released' : CaseProgress::label($status);
    }
}
