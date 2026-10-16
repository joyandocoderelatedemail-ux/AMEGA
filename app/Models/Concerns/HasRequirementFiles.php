<?php

namespace App\Models\Concerns;

/**
 * A desk case whose BI requirements are ticked off (a `checklist` array of item keys)
 * or proven with an upload (a `documents` map of item key to stored file).
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
}
