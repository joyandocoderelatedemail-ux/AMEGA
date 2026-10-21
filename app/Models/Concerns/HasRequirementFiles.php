<?php

namespace App\Models\Concerns;

use App\Support\CaseProgress;

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

    /** Where the case stands in the desk's workflow (see CaseProgress); in preparation until updated. */
    public function progressKey(): string
    {
        return array_key_exists((string) $this->workflow_status, CaseProgress::OPTIONS) ? $this->workflow_status : CaseProgress::DEFAULT;
    }

    public function progressLabel(): string
    {
        return CaseProgress::label($this->progressKey());
    }
}
