<?php

namespace App\Models\Concerns;

/**
 * A desk case whose BI requirements are ticked off (a `checklist` array of item keys)
 * or proven with an upload (a `documents` map of item key to stored file). Each case
 * also has a reference number and a filing status, shown on the folder and the counter.
 */
trait HasRequirementFiles
{
    /** An item is in hand when it is ticked or its file was uploaded. */
    public function hasChecked(string $item): bool
    {
        return in_array($item, $this->checklist ?? [], true) || $this->documentFor($item) !== null;
    }

    /**
     * @return array{path: string, name: string, size: int, mime: string}|null
     */
    public function documentFor(string $item): ?array
    {
        $document = ($this->documents ?? [])[$item] ?? null;

        return is_array($document) && filled($document['path'] ?? null) ? $document : null;
    }

    public function isOpen(): bool
    {
        return $this->released_on === null;
    }

    /** The prefix of this case's reference number, e.g. "OS" for an overstay. */
    abstract public function referencePrefix(): string;

    /** "OS-2026-0014": prefix, the year it was opened, and its number. */
    public function reference(): string
    {
        if (! $this->exists) {
            return 'New';
        }

        return $this->referencePrefix().'-'.($this->created_at ?? now())->format('Y').'-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    /** released, filed (with the BI, not released yet) or in_progress (collecting requirements). */
    public function statusKey(): string
    {
        return match (true) {
            $this->released_on !== null => 'released',
            $this->filed_on !== null => 'filed',
            default => 'in_progress',
        };
    }

    public function statusLabel(): string
    {
        return ['released' => 'Released', 'filed' => 'Filed with the BI', 'in_progress' => 'Collecting requirements'][$this->statusKey()];
    }
}
