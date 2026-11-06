{{--
    The Document Checklist card: the files held on the case and the upload form. Expects $application, $documentTypes;
    $embedded drops the card's own border so it sits inside a larger card (E-Visa Step 3).
--}}
    <div id="documents" class="scroll-mt-32 {{ ! empty($embedded) ? '' : 'rounded-2xl bg-white border border-gray-200 shadow-card overflow-hidden' }}">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
            <div>
                <h2 class="font-heading text-sm font-extrabold text-dark">Document Checklist</h2>
                <p class="text-[11px] text-dark/45 mt-0.5">Held privately &mdash; never served from the public web root</p>
            </div>
            <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40">{{ $application->documents->count() }} filed</span>
        </div>

        @if($application->documents->isEmpty())
            <div class="px-6 py-10 text-center">
                <i data-lucide="file-stack" class="w-7 h-7 text-dark/20 mx-auto mb-2"></i>
                <p class="text-xs font-bold text-dark/50">Nothing filed yet.</p>
            </div>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach($application->documents as $document)
                    <li class="px-6 py-3.5 flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="w-9 h-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                <i data-lucide="file-text" class="w-4 h-4"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-dark truncate">{{ $document->original_name ?: basename($document->file_path) }}</p>
                                <p class="text-[10px] text-dark/45">
                                    {{ str_replace('_', ' ', $document->document_type) }}@if ($document->remarks): {{ $document->remarks }}@endif
                                    @if($document->applicant)
                                        <span class="text-dark/30">&middot;</span> {{ $document->applicant->full_name }}
                                    @endif
                                    @if($document->file_size)
                                        <span class="text-dark/30">&middot;</span> {{ number_format($document->file_size / 1024, 0) }} KB
                                    @endif
                                </p>
                            </div>
                        </div>
                        <a href="{{ route('visa.documents.download', $document) }}"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 text-dark/70 font-bold text-[11px] rounded-lg hover:bg-primary hover:text-white transition-colors shrink-0">
                            <i data-lucide="download" class="w-3.5 h-3.5"></i>
                            <span>Download</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="px-6 py-5 border-t border-gray-100 bg-gray-50/60">
            {{-- E-Visa and Non-Immigrant files are not filed per applicant: an "other" document says what it is in Remarks instead. --}}
            @php $perFile = $application->isFeeFirst() || $application->usesSteps(); @endphp
            <form method="POST" action="{{ route('visa.documents.store', $application) }}" enctype="multipart/form-data" x-data="{ type: {{ Js::from($documentTypes[0] ?? '') }} }">
                @csrf
                <div class="grid grid-cols-1 gap-3 {{ $perFile ? 'sm:grid-cols-3' : 'sm:grid-cols-4' }}">
                    <div>
                        <label for="document_type" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Document Type</label>
                        <select id="document_type" name="document_type" x-model="type"
                                class="w-full px-3 py-2 rounded-xl bg-white border border-gray-200 text-dark text-xs font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                            @foreach($documentTypes as $type)
                                <option value="{{ $type }}">{{ ucwords(str_replace('_', ' ', $type)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if ($perFile)
                    <div x-show="type === 'other'" x-cloak>
                        <label for="doc_remarks" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Remarks</label>
                        <input id="doc_remarks" type="text" name="remarks" maxlength="255" placeholder="What is this document?" :disabled="type !== 'other'"
                               class="w-full px-3 py-2 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    @else
                    <div>
                        <label for="visa_applicant_id" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Applicant</label>
                        <select id="visa_applicant_id" name="visa_applicant_id"
                                class="w-full px-3 py-2 rounded-xl bg-white border border-gray-200 text-dark text-xs font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="">Whole file</option>
                            @foreach($application->applicants as $applicant)
                                <option value="{{ $applicant->id }}">#{{ $applicant->applicant_number }} {{ $applicant->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    <div>
                        <label for="file" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">File</label>
                        <input id="file" type="file" name="file" required
                               accept=".jpg,.jpeg,.png,.webp,.pdf"
                               class="w-full px-3 py-1.5 rounded-xl bg-white border border-gray-200 text-dark text-xs file:mr-2 file:py-1 file:px-3 file:rounded-lg file:border-0 file:bg-primary file:text-white file:text-[11px] file:font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div class="flex items-end">
                        <button type="submit"
                                class="w-full px-4 py-2 bg-primary text-white font-bold text-xs rounded-xl hover:bg-primary-light transition-all">
                            File Document
                        </button>
                    </div>
                </div>
                <p class="text-[10px] text-dark/45 mt-2">JPG, PNG, WebP or PDF up to 10 MB. Stored privately and released only through this counter.</p>
            </form>
        </div>
    </div>
