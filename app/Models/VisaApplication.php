<?php

namespace App\Models;

use App\Models\Concerns\NeedsAdminApproval;
use App\Models\Scopes\OwnFilesScope;
use App\Support\VisaChecklist;
use App\Support\VisaPlaybook;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A visa assistance counter file. Covers any of the three services the counter
 * runs: visit visa, e-Visa, and passporting.
 */
#[ScopedBy([OwnFilesScope::class])]
class VisaApplication extends Model
{
    use HasFactory, NeedsAdminApproval;

    /** The three services at one counter. */
    public const SERVICE_TYPES = ['visit_visa', 'e_visa', 'passporting'];

    /** What the counter calls each service. */
    public const SERVICE_LABELS = [
        'visit_visa' => 'Non-Immigrant',
        'e_visa' => 'E-Visa',
        'passporting' => 'Passporting',
    ];

    /** Visit visa only: the purpose decides the requirement list. */
    public const PURPOSES = ['tourist', 'business', 'family'];

    /** Visit visa only: rush adds a fee and shortens the embassy turnaround. */
    public const PROCESSING_SPEEDS = ['regular', 'rush'];

    /** Passporting only: two different jobs from here on. */
    public const PASSPORT_TYPES = ['foreign', 'local'];

    /** e-Visa only: the steps are identical, only the file size differs. */
    public const APPLICANT_TYPES = ['individual', 'group'];

    public const PAYMENT_TYPES = ['deposit', 'full'];

    /** How an embassy or processing office answered. */
    public const RESULTS = ['approved', 'denied', 'withdrawn'];

    /** Destinations where no embassy appearance is needed. */
    public const NO_APPEARANCE_COUNTRIES = ['Australia', 'New Zealand'];

    /** Countries the foreign passporting route covers. */
    public const FOREIGN_PASSPORT_COUNTRIES = ['USA', 'Canada', 'Australia', 'United Kingdom'];

    public const STATUSES = [
        'pending', 'requirements', 'agreement', 'insurance', 'etravel', 'payment',
        'acknowledged', 'drafting', 'appointment', 'lodged', 'result', 'released', 'cancelled',
    ];

    /**
     * The stages each service walks through, in the counter flowchart's order.
     * Staff record each stage's work on the file before it can advance.
     *
     * @var array<string, list<string>>
     */
    public const SERVICE_STAGES = [
        // Non-Immigrant, six steps: inquiry; agreement and down payment; documents in and
        // acknowledged; forms drafted; submission (fully paid); result and release. The file
        // stays on the last step until it is released, then its status becomes "released".
        // Insurance and e-Travel are add-ons on the file, not stages.
        'visit_visa' => [
            'pending', 'agreement', 'requirements', 'drafting', 'lodged', 'result',
        ],
        'e_visa' => [
            'pending', 'requirements', 'payment', 'agreement', 'lodged', 'released',
        ],
        'passporting' => [
            'pending', 'requirements', 'appointment', 'payment', 'lodged', 'released',
        ],
    ];

    /**
     * Passporting splits on the passport: the Philippine passport goes through
     * a DFA appointment, the foreign one through the embassy.
     *
     * @var array<string, list<string>>
     */
    public const PASSPORT_STAGES = [
        'local' => ['pending', 'requirements', 'appointment', 'payment', 'lodged', 'released'],
        'foreign' => ['pending', 'requirements', 'payment', 'lodged', 'released'],
    ];

    /** The Non-Immigrant flow's own names for its stages. */
    public const VISIT_VISA_LABELS = [
        'pending' => 'Inquiry / Assessment',
        'agreement' => 'Agreement & Payment',
        'requirements' => 'Documents',
        'lodged' => 'Submission',
        'result' => 'Result & Release',
        'released' => 'Released',
    ];

    /** What each stage is called on the counter. */
    public const STAGE_LABELS = [
        'pending' => 'File Opened',
        'requirements' => 'Requirements',
        'agreement' => 'Agreement',
        'insurance' => 'Travel Insurance',
        'etravel' => 'E-Travel',
        'payment' => 'Payment',
        'acknowledged' => 'Acknowledgment',
        'drafting' => 'Drafting of Forms',
        'appointment' => 'DFA Appointment',
        'lodged' => 'Processing',
        'result' => 'Application Result',
        'released' => 'Released',
        'cancelled' => 'Cancelled',
    ];

    protected $fillable = [
        'reference',
        'created_by',
        'service_type',
        'status',
        'applicant_type',
        'client_name',
        'client_email',
        'client_phone',
        'destination_country',
        'purpose',
        'checklist_key',
        'checklist_progress',
        'requires_appearance',
        'processing_speed',
        'passport_type',
        'embassy_country',
        'appointment_at',
        'agreement_signed_at',
        'acknowledgement_signed_at',
        'lodged_at',
        'embassy_reference',
        'result',
        'result_at',
        'result_reference',
        'result_validity',
        'insurance_included',
        'insurance_provider',
        'insurance_policy_number',
        'insurance_declined',
        'etravel_reference',
        'biometrics_at',
        'processing_days',
        'drafted_by',
        'drafted_at',
        'assessed_by',
        'assessed_at',
        'playbook_progress',
        'additional_requests',
        'service_fee',
        'visa_fee',
        'rush_fee',
        'insurance_fee',
        'etravel_fee',
        'total_amount',
        'amount_paid',
        'currency',
        'payment_type',
        'remarks',
        'stage_notified_at',
        'approval_status',
        'approval_requested_at',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'acknowledged_by',
        'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'created_by' => 'integer',
            'requires_appearance' => 'boolean',
            'insurance_included' => 'boolean',
            'insurance_declined' => 'boolean',
            'stage_notified_at' => 'array',
            'appointment_at' => 'datetime',
            'agreement_signed_at' => 'datetime',
            'acknowledgement_signed_at' => 'datetime',
            'drafted_at' => 'datetime',
            'assessed_at' => 'datetime',
            'biometrics_at' => 'date',
            'playbook_progress' => 'array',
            'checklist_progress' => 'array',
            'additional_requests' => 'array',
            'lodged_at' => 'date',
            'result_at' => 'date',
            'service_fee' => 'decimal:2',
            'visa_fee' => 'decimal:2',
            'rush_fee' => 'decimal:2',
            'insurance_fee' => 'decimal:2',
            'etravel_fee' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'approval_requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function applicants(): HasMany
    {
        return $this->hasMany(VisaApplicant::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(VisaApplicationDocument::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The stages this particular file should walk through.
     *
     * @return list<string>
     */
    public function stages(): array
    {
        if ($this->service_type === 'passporting' && isset(self::PASSPORT_STAGES[$this->passport_type])) {
            return self::PASSPORT_STAGES[$this->passport_type];
        }

        return self::SERVICE_STAGES[$this->service_type] ?? self::STATUSES;
    }

    /**
     * A stage's name on this file: the embassy stages read differently per service.
     */
    public function stageLabel(string $stage): string
    {
        return match (true) {
            $this->service_type === 'visit_visa' && isset(self::VISIT_VISA_LABELS[$stage]) => self::VISIT_VISA_LABELS[$stage],
            $stage === 'released' && $this->service_type === 'e_visa' => 'e-Visa Released',
            $stage === 'released' && $this->service_type === 'passporting' => 'Passport Released',
            $stage === 'agreement' && $this->service_type === 'e_visa' => 'Booking Agreement',
            default => self::STAGE_LABELS[$stage] ?? ucfirst(str_replace('_', ' ', $stage)),
        };
    }

    /**
     * Put the file's client on it as the next applicant (the primary one on an
     * empty file), filled from their client profile where there is one, so
     * staff don't retype someone the counter already knows.
     */
    public function addClientAsApplicant(?User $client = null): VisaApplicant
    {
        $number = ((int) $this->applicants()->max('applicant_number')) + 1;

        if ($client && ($client->first_name || $client->last_name)) {
            $name = [
                'first_name' => $client->first_name ?: $client->last_name,
                'middle_name' => $client->middle_name,
                'last_name' => $client->last_name ?: $client->first_name,
                'suffix' => $client->suffix,
            ];
        } else {
            // Only a full name on file: the last word is taken as the surname.
            $parts = preg_split('/\s+/', trim((string) ($client?->name ?: $this->client_name))) ?: [];
            $lastName = count($parts) > 1 ? array_pop($parts) : ($parts[0] ?? 'Applicant');
            $name = ['first_name' => implode(' ', $parts) ?: $lastName, 'last_name' => $lastName];
        }

        return $this->applicants()->create($name + [
            'applicant_number' => $number,
            'is_primary' => $number === 1,
            'date_of_birth' => $client?->date_of_birth,
            'gender' => $client?->gender,
            'nationality' => $client?->nationality,
            'passport_number' => $client?->passport_number,
            'passport_expiry_date' => $client?->passport_expiry,
            'passport_country' => $client?->passport_country,
        ]);
    }

    /**
     * The stages this file has finished: every one before its current stage,
     * and the last one once the file reaches it. A cancelled file has none.
     *
     * @return list<string>
     */
    public function completedStages(): array
    {
        $stages = $this->stages();

        if ($this->isReleasedVisit()) {
            return $stages;
        }

        $current = array_search($this->status, $stages, true);

        if ($current === false) {
            return [];
        }

        // Elsewhere the last stage is done on arrival; a Non-Immigrant file's waits for its release.
        $lastIsDone = $current === count($stages) - 1 && $this->service_type !== 'visit_visa';

        return array_slice($stages, 0, $lastIsDone ? $current + 1 : $current);
    }

    /**
     * When staff last emailed the client that this stage was done.
     */
    public function stageNotifiedAt(string $stage): ?Carbon
    {
        $sentAt = $this->stage_notified_at[$stage] ?? null;

        // Stored with its offset (older entries are UTC), so show it in local time.
        return $sentAt ? Carbon::parse($sentAt)->setTimezone(config('app.timezone')) : null;
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->stageLabel((string) $this->status);
    }

    /**
     * The documents the Requirements stage waits for, from the counter
     * flowcharts. Per-applicant documents are needed once for every applicant.
     *
     * @return list<array{type: string, label: string, per_applicant: bool}>
     */
    public function requiredDocuments(): array
    {
        return match (true) {
            $this->service_type === 'passporting' && $this->passport_type === 'local' => [
                ['type' => 'valid_id', 'label' => 'Valid ID', 'per_applicant' => true],
                ['type' => 'birth_certificate', 'label' => 'PSA Birth Certificate', 'per_applicant' => true],
            ],
            $this->service_type === 'passporting' => [
                ['type' => 'photo', 'label' => 'Photo (embassy specification)', 'per_applicant' => true],
            ],
            default => [
                ['type' => 'passport_scan', 'label' => 'Passport copy', 'per_applicant' => true],
            ],
        };
    }

    /**
     * Required documents not filed yet, as "Passport copy — Juan Dela Cruz".
     *
     * @return list<string>
     */
    public function missingDocuments(): array
    {
        if ($this->service_type === 'visit_visa' && $this->checklist() !== null) {
            return $this->checklistMissing();
        }

        $documents = $this->documents;
        $missing = [];

        foreach ($this->requiredDocuments() as $required) {
            if (! $required['per_applicant']) {
                if (! $documents->contains('document_type', $required['type'])) {
                    $missing[] = $required['label'];
                }

                continue;
            }

            foreach ($this->applicants as $applicant) {
                $filed = $documents->contains(fn ($document) => $document->document_type === $required['type']
                    && $document->visa_applicant_id === $applicant->id);

                if (! $filed) {
                    $missing[] = "{$required['label']} — {$applicant->full_name}";
                }
            }
        }

        return $missing;
    }

    /**
     * Why the file cannot leave its current stage yet, or null when that
     * stage's work is recorded and it may advance.
     */
    public function stageBlocker(): ?string
    {
        // Nothing moves on until the admin has approved the file.
        if ($held = $this->approvalBlocker('advanced')) {
            return $held;
        }

        if ($this->service_type === 'visit_visa') {
            return $this->visitVisaBlocker();
        }

        return match ($this->status) {
            'pending' => $this->applicants->isEmpty()
                ? 'Add at least one applicant to the file.'
                : null,
            'requirements' => ($missing = $this->missingDocuments()) !== []
                ? 'Still missing: '.implode('; ', $missing).'.'
                : null,
            'agreement' => $this->agreement_signed_at ? null : 'Record that the client signed the agreement.',
            'insurance' => ($this->insurance_declined || filled($this->insurance_policy_number))
                ? null
                : 'Record the insurance policy, or that the client declined insurance.',
            'etravel' => filled($this->etravel_reference) ? null : 'Enter the e-Travel reference.',
            'payment' => $this->isFullyPaid()
                ? null
                : 'The file must be fully paid. Balance: '.$this->currency.' '.number_format($this->outstandingBalance(), 2).'.',
            'acknowledged' => $this->acknowledgement_signed_at ? null : 'Record that the client signed the Acknowledgment of Documents.',
            'appointment' => $this->appointment_at ? null : 'Enter the DFA appointment date.',
            'lodged' => filled($this->result) ? null : 'Record the application result.',
            default => null,
        };
    }

    /**
     * The Non-Immigrant flow's gates: the country playbook's ticked tasks first,
     * then what the stage records. Submission needs the file fully paid, which
     * is checked when drafting is done.
     */
    private function visitVisaBlocker(): ?string
    {
        // At Documents the missing documents come before the country's own ticks.
        if ($this->status === 'requirements' && ($missing = $this->missingDocuments()) !== []) {
            return 'Still missing: '.implode('; ', $missing).'.';
        }

        if ($open = $this->openTasks((string) $this->status)) {
            return 'Still to do: '.implode('; ', $open).'.';
        }

        return match ($this->status) {
            'pending' => $this->applicants->isEmpty() ? 'Add at least one applicant to the file.' : null,
            // Agreement & Payment: signed, and a down payment (or the full amount) taken.
            'agreement' => match (true) {
                $this->agreement_signed_at === null => 'Record that the client signed the agreement.',
                (float) $this->amount_paid <= 0 => 'Record the down payment (or the full amount) in Fees. Nothing has been paid yet.',
                default => null,
            },
            // Documents: everything on the checklist in, and the Acknowledgment signed.
            'requirements' => match (true) {
                ($missing = $this->missingDocuments()) !== [] => 'Still missing: '.implode('; ', $missing).'.',
                $this->acknowledgement_signed_at === null => 'Record that the client signed the Acknowledgment of Documents.',
                default => null,
            },
            'drafting' => match (true) {
                $this->draftedForms()->isEmpty() => 'Upload the drafted forms, so the file keeps a copy.',
                $this->drafted_at === null => 'Record that the forms are drafted.',
                $this->openRequests() !== [] => 'Additional documents are still requested: '.implode('; ', array_column($this->openRequests(), 'label')).'.',
                default => null,
            },
            // Submission is where the balance is settled: the file must be fully paid.
            'lodged' => match (true) {
                ! $this->isFullyPaid() => 'The file must be fully paid before submission. Balance: '.$this->currency.' '.number_format($this->outstandingBalance(), 2).'.',
                $this->lodged_at === null => 'Enter the date the application was submitted.',
                default => null,
            },
            'result' => filled($this->result) ? null : 'Record the application result.',
            default => null,
        };
    }

    /**
     * The document checklist this Non-Immigrant file follows, if its destination has one.
     *
     * @return array<string, mixed>|null
     */
    public function checklist(): ?array
    {
        return VisaChecklist::find($this->destination_country, $this->checklist_key);
    }

    /**
     * Has a row been received? Original or copy ticked, or its upload filed. $owner is
     * "applicant:{id}" or "sponsor".
     *
     * @param  array<string, mixed>  $row
     */
    public function checklistReceived(string $owner, array $row): bool
    {
        $ticked = ($this->checklist_progress ?? [])[$owner][$row['id']] ?? [];

        if (! empty($ticked['orig']) || ! empty($ticked['copy'])) {
            return true;
        }

        // A scan attached to this row at Receiving Documents (sponsor scans have no applicant).
        $ownerApplicant = str_starts_with($owner, 'applicant:') ? (int) substr($owner, strlen('applicant:')) : null;

        if ($this->documents->contains(fn ($document) => $document->checklist_item === $row['id'] && $document->visa_applicant_id === $ownerApplicant)) {
            return true;
        }

        if (isset($row['doc']) && str_starts_with($owner, 'applicant:')) {
            $applicantId = (int) substr($owner, strlen('applicant:'));

            return $this->documents->contains(fn ($document) => $document->document_type === $row['doc'] && $document->visa_applicant_id === $applicantId);
        }

        return false;
    }

    /**
     * What the checklist still waits on, as "Row — Applicant": the gating sections' required
     * rows, and one item of every required group. A minor's section gates only for a minor.
     *
     * @return list<string>
     */
    public function checklistMissing(): array
    {
        $variant = $this->checklist();
        $missing = [];

        foreach ($variant['sections'] ?? [] as $section) {
            if ($section['owner'] !== 'applicant' || ! $section['gating']) {
                continue;
            }

            foreach ($this->applicants as $applicant) {
                if ($section['gating'] === 'minor' && ! $applicant->isMinor()) {
                    continue;
                }

                $owner = 'applicant:'.$applicant->id;

                foreach ($section['rows'] as $row) {
                    if (! empty($row['optional'])) {
                        continue;
                    }

                    if (isset($row['items'])) {
                        $any = collect($row['items'])->contains(fn ($item) => $this->checklistReceived($owner, $item));

                        if (! $any) {
                            $missing[] = $row['label'].' (any one) — '.$applicant->full_name;
                        }

                        continue;
                    }

                    if (! $this->checklistReceived($owner, $row)) {
                        $missing[] = $row['label'].' — '.$applicant->full_name;
                    }
                }
            }
        }

        return $missing;
    }

    /**
     * A Non-Immigrant file past its last step: the result is in and it has been released.
     */
    public function isReleasedVisit(): bool
    {
        return $this->service_type === 'visit_visa' && $this->status === 'released';
    }

    /** Marks an upload as a copy of the drafted application forms (Drafting of Forms). */
    public const DRAFTED_FORM = '_drafted_form';

    /**
     * The copies of the drafted forms uploaded at Drafting of Forms.
     *
     * @return Collection<int, VisaApplicationDocument>
     */
    public function draftedForms(): Collection
    {
        return $this->documents->where('checklist_item', self::DRAFTED_FORM)->values();
    }

    /**
     * The country playbook's tasks for a stage that are not ticked yet.
     *
     * @return list<string>
     */
    public function openTasks(string $stage): array
    {
        return array_values(array_diff_key(
            VisaPlaybook::tasks($this->destination_country, $stage),
            $this->playbook_progress ?? [],
        ));
    }

    public function taskDone(string $task): bool
    {
        return isset(($this->playbook_progress ?? [])[$task]);
    }

    /**
     * Extra documents the embassy or portal asked for that have not come in.
     *
     * @return list<array{label: string, requested_at: string, received_at: ?string}>
     */
    public function openRequests(): array
    {
        return array_values(array_filter($this->additional_requests ?? [], fn (array $request) => empty($request['received_at'])));
    }

    /**
     * What the counter does next on this file, for the banner at the top of its
     * page. A stage waiting on work is "todo" (with the reason and where to do
     * it), a finished one is "ready" to advance, and the last stage is "done".
     *
     * @return array{state: string, title: string, detail: ?string, anchor: ?string, action: ?string, step: int, steps: int, next: ?string}
     */
    public function nextStep(): array
    {
        $stages = $this->stages();
        $found = array_search($this->status, $stages, true);
        $index = $this->isReleasedVisit() ? count($stages) - 1 : ($found === false ? 0 : $found);
        $next = $stages[$index + 1] ?? null;

        // A Non-Immigrant file's last step moves on to its release.
        if ($this->service_type === 'visit_visa' && $this->status === 'result') {
            $next = 'released';
        }

        $position = ['step' => $index + 1, 'steps' => count($stages), 'next' => $next ? $this->stageLabel($next) : null];
        $none = ['anchor' => null, 'action' => null];

        if ($this->status === 'cancelled') {
            return ['state' => 'cancelled', 'title' => 'This file was cancelled', 'detail' => 'It is off the pipeline. There is nothing more to do.'] + $none + $position;
        }

        if ($this->isReleasedVisit()) {
            return ['state' => 'done', 'title' => 'File complete', 'detail' => 'The result is in and the file has been released.'] + $none + $position;
        }

        if ($next === null) {
            return ['state' => 'done', 'title' => 'File complete', 'detail' => 'Every step is done: '.$this->status_label.'.'] + $none + $position;
        }

        $blocker = $this->stageBlocker();

        if ($blocker === null) {
            return ['state' => 'ready', 'title' => $this->status_label.' is done', 'detail' => 'Move the file on to '.$position['next'].'.'] + $none + $position;
        }

        $docsMissing = $this->status === 'requirements' && $this->missingDocuments() !== [];

        [$title, $anchor, $action] = $this->service_type === 'visit_visa' && ! $docsMissing && $this->openTasks((string) $this->status) !== []
            ? ['Finish '.$this->status_label, 'current-step', 'Go to this step']
            : match ($this->status) {
                'pending' => ['Add the applicant', 'applicants', 'Go to applicants'],
                'requirements' => $this->service_type === 'visit_visa' && $this->missingDocuments() === []
                    ? ['Get the Acknowledgment of Documents signed', 'current-step', 'Go to this step']
                    : ['Collect the documents', 'documents', 'Go to documents'],
                'agreement' => $this->service_type === 'visit_visa' && $this->agreement_signed_at
                    ? ['Collect the down payment', 'fees', 'Go to payment']
                    : ['Get the agreement signed', 'current-step', 'Go to this step'],
                'insurance' => ['Record the insurance', 'current-step', 'Go to this step'],
                'etravel' => ['Enter the e-Travel reference', 'current-step', 'Go to this step'],
                'payment' => [$this->service_type === 'visit_visa' ? 'Collect the down payment' : 'Collect the payment', 'fees', 'Go to payment'],
                'acknowledged' => ['Get the Acknowledgment of Documents signed', 'current-step', 'Go to this step'],
                'appointment' => ['Set the DFA appointment', 'current-step', 'Go to this step'],
                'drafting' => ['Draft and check the forms', 'current-step', 'Go to this step'],
                'lodged' => match (true) {
                    $this->service_type !== 'visit_visa' => ['Record the result', 'current-step', 'Go to this step'],
                    ! $this->isFullyPaid() => ['Collect the balance', 'fees', 'Go to payment'],
                    default => ['Record the submission', 'current-step', 'Go to this step'],
                },
                'result' => ['Record the result', 'current-step', 'Go to this step'],
                default => ['Finish '.$this->status_label, 'current-step', 'Go to this step'],
            };

        // A long list of open country steps reads as a count and a list, not one run-on sentence.
        $tasks = $this->service_type === 'visit_visa' && ! $docsMissing ? $this->openTasks((string) $this->status) : [];

        if (count($tasks) > 2) {
            return ['state' => 'todo', 'title' => $title, 'anchor' => $anchor, 'action' => $action,
                'detail' => count($tasks).' steps still to do.', 'items' => $tasks, 'more' => 'in this step below',
            ] + $position;
        }

        // Likewise a long list of missing documents.
        $missing = $tasks === [] && $this->status === 'requirements' ? $this->missingDocuments() : [];

        if (count($missing) > 2) {
            $people = collect($missing)->map(fn (string $line) => str_contains($line, ' — ') ? substr($line, strrpos($line, ' — ') + strlen(' — ')) : null)->filter()->unique();
            $single = $people->count() === 1;

            return ['state' => 'todo', 'title' => $title, 'anchor' => $anchor, 'action' => $action,
                'detail' => count($missing).' documents still missing'.($single ? ' for '.$people->first() : '').'.',
                'items' => $single ? array_map(fn (string $line) => substr($line, 0, strrpos($line, ' — ')), $missing) : $missing,
            ] + $position;
        }

        return ['state' => 'todo', 'title' => $title, 'detail' => $blocker, 'anchor' => $anchor, 'action' => $action] + $position;
    }

    /**
     * Paid in full: something is billed and nothing is outstanding.
     */
    public function isFullyPaid(): bool
    {
        return (float) $this->total_amount > 0 && $this->outstandingBalance() <= 0;
    }

    public function isRush(): bool
    {
        return $this->processing_speed === 'rush';
    }

    /**
     * e-Visa files covering several pax are group packages.
     */
    public function isGroupPackage(): bool
    {
        return $this->applicant_type === 'group';
    }

    /**
     * A visit visa to Australia or New Zealand needs no embassy appearance.
     */
    public function needsAppearance(): bool
    {
        return ! in_array($this->destination_country, self::NO_APPEARANCE_COUNTRIES, true);
    }

    public function outstandingBalance(): float
    {
        return max(0, (float) $this->total_amount - (float) $this->amount_paid);
    }

    /**
     * Generate a unique visa assistance reference code.
     */
    public static function generateReference(): string
    {
        return 'VSA-'.date('Y').'-'.strtoupper(substr(uniqid(), -5));
    }
}
