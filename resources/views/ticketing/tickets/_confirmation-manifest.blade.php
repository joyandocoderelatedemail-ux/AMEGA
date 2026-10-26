{{-- Part of the booking confirmation (_confirmation.blade.php): The passenger manifest with each passenger's documents (and uploads for what is missing). --}}
        <!-- Passenger Manifest & Documents (Edit Booking links here to upload documents) -->
        <div id="passengers" class="space-y-4 scroll-mt-32">
            <h2 class="text-base font-heading font-bold text-dark flex items-center gap-2">
                <i data-lucide="users" class="w-5 h-5 text-primary"></i>
                <span>Passenger Manifest &amp; Document Credentials</span>
            </h2>

            <div class="space-y-4">
                @foreach($ticket->passengers as $p)
                    <div class="p-5 rounded-2xl border border-gray-200 bg-white space-y-3">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-gray-100 pb-3">
                            <div class="flex items-center gap-2.5">
                                <span class="w-6 h-6 rounded-full bg-primary text-white text-xs font-bold flex items-center justify-center">
                                    {{ $p->passenger_number }}
                                </span>
                                <span class="font-heading font-bold text-sm text-dark">{{ $p->full_name }}</span>
                                <span class="text-[10px] px-2 py-0.5 rounded-md font-bold uppercase {{ $p->nationality_type === 'filipino' ? 'bg-primary/10 text-primary' : 'bg-accent/20 text-accent-dark' }}">
                                    {{ $p->nationality_type === 'filipino' ? '🇵🇭 Filipino' : '🌐 Foreign National' }}
                                </span>
                                @if($p->gender)
                                    <span class="text-[10px] px-2 py-0.5 rounded-md font-bold uppercase bg-gray-100 text-dark/70 capitalize">{{ $p->gender }}</span>
                                @endif
                            </div>
                            <div class="text-xs text-dark/60 font-medium">
                                Passenger Type: <strong class="capitalize text-dark">{{ $p->passenger_type }}</strong>
                                @if($p->travel_tax_included)
                                    • <span class="text-primary font-bold">Travel Tax Included</span>
                                @endif
                                @if($p->visa_status)
                                    • <span class="text-blue-700 font-bold uppercase text-[10px]">{{ str_replace('_', ' ', $p->visa_status) }}</span>
                                @endif
                            </div>
                        </div>

                        <!-- Details Grid -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                            @if($p->date_of_birth)
                                <div>
                                    <span class="text-[10px] text-dark/40 font-bold uppercase block">Date of Birth</span>
                                    <span class="font-semibold text-dark">{{ $p->date_of_birth->format('M d, Y') }}</span>
                                </div>
                            @endif
                            @if($p->passport_number)
                                <div>
                                    <span class="text-[10px] text-dark/40 font-bold uppercase block">Passport Number</span>
                                    <span class="font-mono font-bold text-dark">{{ $p->passport_number }}</span>
                                </div>
                            @endif
                            @if($p->passport_expiry_date)
                                <div>
                                    <span class="text-[10px] text-dark/40 font-bold uppercase block">Passport Expiry</span>
                                    <span class="font-semibold text-dark">{{ $p->passport_expiry_date->format('M d, Y') }}</span>
                                </div>
                            @endif
                            @if($p->visa_type && $p->visa_type !== 'none')
                                <div>
                                    <span class="text-[10px] text-dark/40 font-bold uppercase block">Visa Type</span>
                                    <span class="font-semibold text-dark">{{ ucfirst(str_replace('_', ' ', $p->visa_type)) }}</span>
                                </div>
                            @endif
                        </div>

                        <!-- Attached Uploaded Documents -->
                        <div class="pt-2">
                            <span class="text-[11px] font-bold text-dark/60 uppercase tracking-wider block mb-2">Attached Documents ({{ $p->documents->count() }})</span>
                            @if($p->documents->count() > 0)
                                <div class="flex flex-wrap gap-2">
                                    @foreach($p->documents as $doc)
                                        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-gray-50 border border-gray-200 text-xs font-semibold">
                                            <i data-lucide="file-check" class="w-3.5 h-3.5 text-emerald-600"></i>
                                            <span class="text-dark">{{ $doc->formatted_type }}:</span>
                                            <span class="text-dark/60 truncate max-w-[150px]">{{ $doc->original_name }}</span>
                                            @if ($documentLinks)
                                            <a href="{{ route('ticketing.documents.download', $doc) }}" 
                                               class="text-primary hover:underline font-bold text-[11px] ml-1 flex items-center gap-0.5 print:hidden">
                                                <i data-lucide="download" class="w-3 h-3"></i> Download
                                            </a>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-xs text-dark/40 italic">No document scans uploaded for this passenger.</span>
                            @endif
                        </div>

                        <!-- Required documents still to come, each with its own upload -->
                        @if(($documentGaps[$p->id]['missing'] ?? []) !== [])
                            <div id="missing-documents-{{ $p->id }}" class="pt-3 border-t border-gray-100 space-y-2 print:hidden scroll-mt-24 target:ring-2 target:ring-amber-300 target:rounded-xl target:p-3">
                                <span class="text-[11px] font-bold text-amber-700 uppercase tracking-wider block">Required documents missing</span>
                                @foreach($documentGaps[$p->id]['missing'] as $type => $label)
                                    <form method="POST" action="{{ route('ticketing.tickets.passengers.documents.store', [$ticket, $p]) }}" enctype="multipart/form-data"
                                          class="flex flex-wrap items-center gap-2 p-3 rounded-xl bg-amber-50 border border-amber-200">
                                        @csrf
                                        <input type="hidden" name="document_type" value="{{ $type }}">
                                        <span class="text-xs font-bold text-amber-900 min-w-[10rem]">{{ $label }}</span>
                                        <input type="file" name="file" required accept="image/jpeg,image/png,image/webp,application/pdf"
                                               class="flex-1 min-w-[12rem] text-xs text-dark/70 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-white file:text-navy-700 hover:file:bg-gray-50 cursor-pointer">
                                        <button type="submit" class="px-3 py-1.5 rounded-lg bg-navy-700 text-white text-xs font-bold hover:bg-navy-800 transition-colors">Upload</button>
                                    </form>
                                @endforeach
                                @error('file')
                                    <p class="text-xs font-semibold text-rose-600">{{ $message }}</p>
                                @enderror
                                @error('document_type')
                                    <p class="text-xs font-semibold text-rose-600">{{ $message }}</p>
                                @enderror
                                <p class="text-[10px] text-dark/40">JPG, PNG, WEBP or PDF, up to 5 MB.</p>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
