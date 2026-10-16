{{--
    Receiving Documents on a Non-Immigrant file: the country checklist, one tab per applicant
    and one for the sponsor. Each requirement is marked ORIG / COPY as it is handed over, and a
    scan can be attached right on its row (click, or drop the file on the row). Either counts
    the row as received; everything saves as it is done, and the progress bar follows.
--}}
@php
    $variant = $application->checklist();
    $progress = $application->checklist_progress ?? [];
    $documents = $application->documents;

    // Scans on a row: attached to it here, or (for the passport copy) filed with the old upload form.
    $filesFor = fn (?int $applicantId, array $row) => $documents
        ->filter(fn ($document) => $document->visa_applicant_id === $applicantId && (
            $document->checklist_item === $row['id']
            || ($document->checklist_item === null && isset($row['doc']) && $applicantId !== null && $document->document_type === $row['doc'])
        ))
        ->map(fn ($document) => \App\Http\Controllers\VisaAssistance\VisaApplicationController::documentPayload($document))
        ->values()->all();

    $owners = $application->applicants
        ->map(fn ($applicant) => ['key' => 'applicant:'.$applicant->id, 'name' => $applicant->full_name, 'kind' => 'applicant', 'id' => $applicant->id, 'minor' => $applicant->isMinor()])
        ->push(['key' => 'sponsor', 'name' => 'Sponsor / inviter', 'kind' => 'sponsor', 'id' => null, 'minor' => false])
        ->map(function (array $owner) use ($variant, $progress, $filesFor) {
            $state = [];
            $remember = function (array $row) use (&$state, $owner, $progress, $filesFor) {
                $ticked = $progress[$owner['key']][$row['id']] ?? [];
                $state[$row['id']] = ['orig' => ! empty($ticked['orig']), 'copy' => ! empty($ticked['copy']), 'files' => $filesFor($owner['id'], $row)];
            };

            $sections = collect($variant['sections'])
                ->filter(fn ($section) => $section['owner'] === $owner['kind'])
                ->map(function ($section) use ($owner, $remember) {
                    $required = $section['gating'] === true || ($section['gating'] === 'minor' && $owner['minor']);

                    return [
                        'label' => $section['label'],
                        'required' => $required,
                        'note' => match (true) {
                            $section['gating'] === 'minor' => $owner['minor'] ? 'Required: the applicant is a minor' : 'Only for a minor applicant',
                            $owner['kind'] === 'sponsor' => 'Tracked only: never holds the file',
                            ! $required => 'Optional',
                            default => 'Required to move on',
                        },
                        'rows' => collect($section['rows'])->map(function ($row) use ($remember) {
                            $remember($row);
                            foreach ($row['items'] ?? [] as $item) {
                                $remember($item);
                            }

                            return ['id' => $row['id'], 'label' => $row['label'], 'optional' => ! empty($row['optional']),
                                'items' => isset($row['items']) ? array_map(fn ($item) => ['id' => $item['id'], 'label' => $item['label']], $row['items']) : null];
                        })->values()->all(),
                    ];
                })->values()->all();

            // Anything handed over that is not on the sheet.
            $remember(['id' => '_other']);
            $sections[] = ['label' => 'Other documents', 'required' => false, 'note' => 'Anything else the client handed over',
                'rows' => [['id' => '_other', 'label' => 'Other document (not on the checklist)', 'optional' => true, 'items' => null]]];

            return $owner + ['sections' => $sections, 'state' => $state];
        })->values()->all();

    $pill = 'inline-flex items-center justify-center gap-1 h-7 px-2.5 rounded-lg border text-[10px] font-extrabold uppercase tracking-wider transition-colors';
@endphp

<div x-data="visaDocs({{ Js::from([
        'owners' => $owners,
        'toggleUrl' => route('visa.applications.checklist', $application),
        'uploadUrl' => route('visa.applications.checklist.upload', $application),
    ]) }})" class="space-y-4">

    <!-- Header: what this is, and the printable sheet -->
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-sm font-bold text-dark">{{ $application->destination_country }} &middot; {{ $variant['label'] }} checklist</p>
            <p class="text-xs text-dark/60 mt-0.5">
                Mark <strong>ORIG</strong> or <strong>COPY</strong> as each document is handed over, or attach its scan right on the row.
                Either one counts it as received. Everything saves as you go.
            </p>
        </div>
        <a href="{{ route('visa.applications.checklist.print', $application) }}" target="_blank" rel="noopener"
           class="inline-flex items-center gap-2 px-3.5 py-2 bg-white border border-gray-200 text-dark/70 font-bold text-xs rounded-xl hover:border-gray-300 transition-all shrink-0">
            <i data-lucide="printer" class="w-4 h-4"></i> Print checklist
        </a>
    </div>

    <p x-show="error" x-cloak x-text="error" class="rounded-xl bg-rose-50 border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700" role="alert"></p>

    <!-- One tab per person, with how far each has got -->
    <div class="flex flex-wrap gap-2" role="tablist" aria-label="Whose documents">
        <template x-for="(owner, o) in owners" :key="owner.key">
            <button type="button" role="tab" @click="active = o" :aria-selected="active === o"
                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl border text-xs font-bold transition-colors"
                    :class="active === o ? 'bg-primary text-white border-primary' : 'bg-white text-dark/70 border-gray-200 hover:border-gray-300'">
                <span x-text="owner.name"></span>
                <span x-show="progress(o).need > 0" class="px-1.5 py-0.5 rounded-md text-[10px] font-extrabold"
                      :class="progress(o).done === progress(o).need ? 'bg-emerald-100 text-emerald-700' : (active === o ? 'bg-white/20 text-white' : 'bg-gray-100 text-dark/60')"
                      x-text="progress(o).done + '/' + progress(o).need"></span>
                <span x-show="progress(o).need === 0" class="text-[10px] font-semibold" :class="active === o ? 'text-white/70' : 'text-dark/40'">optional</span>
            </button>
        </template>
    </div>

    <template x-for="(owner, o) in owners" :key="'panel-' + owner.key">
        <div x-show="active === o" class="space-y-4" role="tabpanel">

            <!-- Progress for this person -->
            <div x-show="progress(o).need > 0" class="rounded-xl bg-gray-50 border border-gray-100 px-4 py-3">
                <div class="flex items-center justify-between gap-3 text-xs">
                    <span class="font-bold text-dark" x-text="progress(o).done === progress(o).need ? 'All required documents received' : progress(o).done + ' of ' + progress(o).need + ' required documents received'"></span>
                    <span class="font-bold" :class="progress(o).done === progress(o).need ? 'text-emerald-700' : 'text-dark/50'" x-text="Math.round(progress(o).done / progress(o).need * 100) + '%'"></span>
                </div>
                <div class="mt-2 h-2 rounded-full bg-gray-200 overflow-hidden">
                    <div class="h-2 rounded-full transition-all duration-300" :class="progress(o).done === progress(o).need ? 'bg-emerald-500' : 'bg-primary'"
                         :style="'width: ' + (progress(o).done / progress(o).need * 100) + '%'"></div>
                </div>
            </div>

            <template x-for="(section, s) in owner.sections" :key="owner.key + '-s' + s">
                <section class="rounded-xl border border-gray-100 overflow-hidden">
                    <div class="px-4 py-2 bg-gray-50 flex flex-wrap items-baseline justify-between gap-2 border-b border-gray-100">
                        <p class="text-[11px] font-extrabold uppercase tracking-wider text-dark/70" x-text="section.label"></p>
                        <p class="text-[10px] font-semibold" :class="section.required ? 'text-amber-700' : 'text-dark/45'" x-text="section.note"></p>
                    </div>
                    <ul class="divide-y divide-gray-100">
                        <template x-for="row in section.rows" :key="owner.key + row.id">
                            <li>
                                <!-- A group: any one of its items will do -->
                                <template x-if="row.items">
                                    <div>
                                        <div class="px-4 py-2 flex items-center justify-between gap-3 bg-white">
                                            <p class="text-xs font-bold text-dark">
                                                <span x-text="row.label"></span>
                                                <span class="font-semibold text-dark/45">&mdash; any one</span>
                                            </p>
                                            <span class="text-[10px] font-extrabold uppercase tracking-wider" :class="groupGot(o, row) ? 'text-emerald-700' : (section.required && !row.optional ? 'text-amber-700' : 'text-dark/40')"
                                                  x-text="groupGot(o, row) ? 'Received' : (section.required && !row.optional ? 'Needed' : 'Optional')"></span>
                                        </div>
                                        <ul class="pl-4 border-l-4 border-gray-100 ml-4 mb-2">
                                            <template x-for="item in row.items" :key="owner.key + item.id">
                                                <li x-html="rowHtml(o, item, true)" class="contents"></li>
                                            </template>
                                        </ul>
                                    </div>
                                </template>
                                <!-- A single requirement -->
                                <template x-if="!row.items">
                                    <div x-html="rowHtml(o, row, false, section.required && !row.optional)"></div>
                                </template>
                            </li>
                        </template>
                    </ul>
                </section>
            </template>
        </div>
    </template>

    <!-- When everything required is in, the step is ready to move on (the banner updates on reload) -->
    <div x-show="allDone()" x-cloak class="rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 flex flex-wrap items-center justify-between gap-3">
        <p class="text-xs font-semibold text-emerald-800">Every required document is in. You can move on to the Acknowledgment.</p>
        <button type="button" @click="window.location.reload()" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white font-bold text-xs rounded-xl hover:bg-emerald-700 transition-colors">
            Continue
        </button>
    </div>

    <input type="file" x-ref="picker" class="hidden" multiple accept=".jpg,.jpeg,.png,.webp,.pdf" @change="picked($event)">
</div>

<script>
    // Receiving Documents: ORIG / COPY marks and scans attached per row, saved as they happen.
    function visaDocs(config) {
        const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
        const icon = {
            clip: '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m18.4 11.1-6.9 6.9a4.5 4.5 0 0 1-6.4-6.4l7.6-7.6a3 3 0 0 1 4.2 4.2l-7.6 7.6a1.5 1.5 0 0 1-2.1-2.1l6.9-6.9"/></svg>',
            check: '<svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 5 5L20 7"/></svg>',
            file: '<svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/></svg>',
            x: '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>',
        };
        const esc = (text) => String(text).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

        return {
            owners: config.owners,
            active: 0,
            error: '',
            busy: {},
            dragging: null,
            target: null,

            state(o, id) {
                return this.owners[o].state[id];
            },
            got(o, id) {
                const s = this.state(o, id);
                return !!s && (s.orig || s.copy || s.files.length > 0);
            },
            groupGot(o, row) {
                return row.items.some((item) => this.got(o, item.id));
            },
            progress(o) {
                let need = 0, done = 0;
                for (const section of this.owners[o].sections) {
                    if (!section.required) continue;
                    for (const row of section.rows) {
                        if (row.optional) continue;
                        need++;
                        if (row.items ? this.groupGot(o, row) : this.got(o, row.id)) done++;
                    }
                }
                return { need, done };
            },
            allDone() {
                return this.owners.every((owner, o) => this.progress(o).done === this.progress(o).need);
            },

            // One requirement row: status, ORIG / COPY, attach, and the scans under it.
            rowHtml(o, row, inGroup, required = false) {
                const s = this.state(o, row.id);
                const key = o + '|' + row.id;
                const got = this.got(o, row.id);
                const status = got
                    ? '<span class="inline-flex items-center gap-1 text-[10px] font-extrabold uppercase tracking-wider text-emerald-700">' + icon.check + 'Received</span>'
                    : (required ? '<span class="text-[10px] font-extrabold uppercase tracking-wider text-amber-700">Needed</span>'
                                : '<span class="text-[10px] font-bold uppercase tracking-wider text-dark/35">' + (inGroup ? '' : 'Optional') + '</span>');
                const pill = (col, label) => '<button type="button" data-act="toggle" data-o="' + o + '" data-id="' + esc(row.id) + '" data-col="' + col + '" aria-pressed="' + s[col] + '" '
                    + 'class="{{ $pill }} ' + (s[col] ? 'bg-primary border-primary text-white' : 'bg-white border-gray-200 text-dark/50 hover:border-primary hover:text-primary') + '">'
                    + (s[col] ? icon.check : '') + label + '</button>';
                const files = s.files.map((file) => '<span class="inline-flex items-center gap-1.5 max-w-full pl-2 pr-1 py-1 rounded-lg bg-primary/5 border border-primary/15 text-[11px] text-dark/80">'
                    + icon.file + '<a href="' + esc(file.url) + '" target="_blank" rel="noopener" class="truncate font-semibold hover:underline" title="Open ' + esc(file.name) + '">' + esc(file.name) + '</a>'
                    + (file.size ? '<span class="text-dark/40 shrink-0">' + esc(file.size) + '</span>' : '')
                    + '<button type="button" data-act="remove" data-o="' + o + '" data-id="' + esc(row.id) + '" data-file="' + file.id + '" class="p-0.5 rounded text-dark/40 hover:text-rose-600 hover:bg-rose-50" title="Remove this file">' + icon.x + '</button></span>').join('');

                return '<div data-row="' + key + '" class="px-4 py-2.5 flex flex-col gap-1.5 ' + (this.dragging === key ? 'bg-primary/5 ring-2 ring-primary ring-inset' : (got ? 'bg-white' : 'bg-white')) + '">'
                    + '<div class="flex flex-wrap items-center gap-x-3 gap-y-2">'
                    + '<p class="flex-1 min-w-0 text-xs ' + (got ? 'text-dark' : 'text-dark/80') + '">' + esc(row.label) + '</p>'
                    + '<div class="flex items-center gap-2 shrink-0">' + status
                    + pill('orig', 'Orig') + pill('copy', 'Copy')
                    + '<button type="button" data-act="attach" data-o="' + o + '" data-id="' + esc(row.id) + '" class="{{ $pill }} bg-white border-gray-200 text-dark/60 hover:border-primary hover:text-primary" title="Attach a scan (or drop a file on this row)">'
                    + (this.busy[key] ? 'Uploading…' : icon.clip + 'Attach') + '</button>'
                    + '</div></div>'
                    + (files ? '<div class="flex flex-wrap gap-1.5">' + files + '</div>' : '')
                    + '</div>';
            },

            init() {
                // The rows are drawn as HTML, so their buttons and file drops are handled here.
                this.$el.addEventListener('click', (event) => {
                    const button = event.target.closest('[data-act]');
                    if (!button) return;
                    const o = Number(button.dataset.o), id = button.dataset.id;
                    if (button.dataset.act === 'toggle') this.toggle(o, id, button.dataset.col);
                    if (button.dataset.act === 'attach') { this.target = { o, id }; this.$refs.picker.click(); }
                    if (button.dataset.act === 'remove') this.remove(o, id, Number(button.dataset.file));
                });
                this.$el.addEventListener('dragover', (event) => {
                    const row = event.target.closest('[data-row]');
                    if (!row) return;
                    event.preventDefault();
                    this.dragging = row.dataset.row;
                });
                this.$el.addEventListener('dragleave', (event) => {
                    if (!event.relatedTarget || !event.currentTarget.contains(event.relatedTarget)) this.dragging = null;
                });
                this.$el.addEventListener('drop', (event) => {
                    const row = event.target.closest('[data-row]');
                    this.dragging = null;
                    if (!row) return;
                    event.preventDefault();
                    const [o, id] = row.dataset.row.split('|');
                    this.upload(Number(o), id, event.dataTransfer.files);
                });
            },

            picked(event) {
                if (this.target) this.upload(this.target.o, this.target.id, event.target.files);
                event.target.value = '';
            },

            async toggle(o, id, column) {
                const s = this.state(o, id);
                s[column] = !s[column];
                this.error = '';
                try {
                    const response = await this.send(config.toggleUrl, JSON.stringify({ owner: this.owners[o].key, item: id, column }), 'application/json');
                    s[column] = (await response.json()).on;
                } catch (e) {
                    s[column] = !s[column];
                    this.error = 'That mark could not be saved. Please try again.';
                }
            },

            async upload(o, id, files) {
                const key = o + '|' + id;
                this.error = '';
                for (const file of files) {
                    if (file.size > 10 * 1024 * 1024) { this.error = file.name + ' is over 10 MB.'; continue; }
                    const body = new FormData();
                    body.append('owner', this.owners[o].key);
                    body.append('item', id);
                    body.append('file', file);
                    this.busy[key] = true;
                    try {
                        const response = await this.send(config.uploadUrl, body);
                        this.state(o, id).files.push((await response.json()).file);
                    } catch (e) {
                        this.error = file.name + ' could not be uploaded. Use a JPG, PNG, WebP or PDF up to 10 MB.';
                    } finally {
                        this.busy[key] = false;
                    }
                }
            },

            async remove(o, id, fileId) {
                const s = this.state(o, id);
                const file = s.files.find((f) => f.id === fileId);
                if (!file || !confirm('Remove ' + file.name + '?')) return;
                try {
                    await this.send(file.delete_url, null, null, 'DELETE');
                    s.files = s.files.filter((f) => f.id !== fileId);
                } catch (e) {
                    this.error = 'That file could not be removed. Please try again.';
                }
            },

            async send(url, body, type = null, method = 'POST') {
                const headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() };
                if (type) headers['Content-Type'] = type;
                const response = await fetch(url, { method, headers, body, credentials: 'same-origin' });
                if (!response.ok) throw new Error('request failed');
                return response;
            },
        };
    }
</script>
