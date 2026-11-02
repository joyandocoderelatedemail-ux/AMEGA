<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * One line of a desk's service list (Immigration, Visa Assistance, SRRV), the same on all three:
 * the files the admin is holding (waiting for approval, or returned with a reason) come first
 * whatever their status, then the work in progress, then the finished files.
 */
class ServiceList
{
    public const ACTION = 'action';

    public const PROGRESS = 'progress';

    public const RELEASED = 'released';

    /** Amber for anything that needs someone to act: waiting for the admin, or returned to the agent. */
    private const HOLD_TONE = 'bg-amber-50 text-amber-800 border-amber-300';

    /**
     * @param  string  $name  who the file is for
     * @param  string  $meta  the reference and what it is about
     * @param  ?string  $approval  the file's approval_status
     * @param  string  $badge  the file's own status, shown when it is not held
     * @param  string  $tone  the badge's colours when it is not held
     * @param  bool  $closed  the work is finished (released, collected, completed)
     * @param  ?string  $note  the admin's reason, shown on a returned file
     * @param  ?\DateTimeInterface  $opened  when the file was opened (shown in the Opened column; null shows a dash)
     * @return array{name: string, meta: string, group: string, badge: string, tone: string, hold: ?string, note: ?string, url: ?string, opened: ?\DateTimeInterface}
     */
    public static function row(string $name, string $meta, ?string $approval, string $badge, string $tone, bool $closed, ?string $note, ?string $url, ?\DateTimeInterface $opened = null): array
    {
        $hold = CaseProgress::approvalHold($approval);

        return [
            'name' => $name,
            'meta' => $meta,
            'group' => match (true) {
                $hold !== null => self::ACTION,
                $closed => self::RELEASED,
                default => self::PROGRESS,
            },
            'badge' => $hold ? CaseProgress::holdLabel($hold) : $badge,
            'tone' => $hold ? self::HOLD_TONE : $tone,
            'hold' => $hold,
            'note' => $hold === CaseProgress::RETURNED ? $note : null,
            'url' => $url,
            'opened' => $opened,
        ];
    }

    /**
     * The rows split into the three sections the list shows, each with its count.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, Collection<int, array<string, mixed>>>
     */
    public static function sections(Collection $rows): array
    {
        return [
            self::ACTION => $rows->where('group', self::ACTION)->values(),
            self::PROGRESS => $rows->where('group', self::PROGRESS)->values(),
            self::RELEASED => $rows->where('group', self::RELEASED)->values(),
        ];
    }
}
