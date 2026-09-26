{{-- The lookup results (or recent clients), refreshed in place while staff type. --}}
@if ($searched)
    @if ($matches->isEmpty() && $clientsWithoutSheet->isEmpty())
        <!-- No match: the new-client path -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-amber-200 shadow-sm">
            <div class="flex items-start gap-4">
                <div class="w-11 h-11 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                    <i data-lucide="user-x" class="w-5 h-5"></i>
                </div>
                <div class="flex-1">
                    <h3 class="font-heading text-lg font-bold text-dark">No client on file for “{{ $search }}”</h3>
                    <p class="text-xs text-dark/60 mt-1 max-w-lg">
                        Treat this as a new client: hand them a blank form to fill in by hand, then key it in here so
                        their next visit is a lookup instead of a rewrite.
                    </p>
                    <div class="flex flex-wrap items-center gap-2 mt-5">
                        <a href="{{ route('admin.client-sheets.blank') }}" target="_blank"
                           class="px-5 py-2.5 bg-white text-primary border border-primary/25 font-bold text-xs rounded-full hover:bg-primary/5 transition-all flex items-center gap-2">
                            <i data-lucide="printer" class="w-4 h-4"></i>
                            Print blank form
                        </a>
                        <a href="{{ route('admin.client-sheets.create', preg_match('/\d/', $search) ? ['passport' => $search] : ['name' => $search]) }}"
                           class="px-5 py-2.5 bg-accent text-dark font-bold text-xs rounded-full hover:bg-accent-dark transition-all shadow-md flex items-center gap-2">
                            <i data-lucide="user-plus" class="w-4 h-4"></i>
                            Create client record
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @else
        @if ($matches->isNotEmpty())
            <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm">
                <h3 class="font-heading text-base font-bold text-dark mb-4">
                    {{ $matches->count() }} {{ Str::plural('match', $matches->count()) }} for “{{ $search }}”
                </h3>
                @include('admin.client-sheets.results', ['clients' => $matches])
            </div>
        @endif

        @if ($clientsWithoutSheet->isNotEmpty())
            <!-- Registered clients (client list, ticketing desk) who have no sheet yet -->
            <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm">
                <h3 class="font-heading text-base font-bold text-dark">Registered clients without a client sheet</h3>
                <p class="text-xs text-dark/50 mt-1 mb-4">Start their sheet from their client profile &mdash; name, contact and passport are filled in.</p>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-gray-200 text-dark font-extrabold uppercase tracking-wider">
                                <th class="pb-3 px-3">Client</th>
                                <th class="pb-3 px-3">Passport</th>
                                <th class="pb-3 px-3">Nationality</th>
                                <th class="pb-3 px-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach ($clientsWithoutSheet as $registered)
                                <tr class="hover:bg-gray-50/50 transition-colors">
                                    <td class="py-4 px-3">
                                        <span class="font-bold text-dark">{{ $registered->full_name }}</span>
                                        <div class="text-[11px] text-dark/50">{{ collect([$registered->email, $registered->phone])->filter()->implode(' · ') ?: 'No contact on file' }}</div>
                                    </td>
                                    <td class="py-4 px-3 font-mono font-bold text-primary whitespace-nowrap">{{ $registered->passport_number ?: '—' }}</td>
                                    <td class="py-4 px-3 text-dark/70">{{ $registered->nationality ?: '—' }}</td>
                                    <td class="py-4 px-3">
                                        <div class="flex items-center justify-end">
                                            <a href="{{ route('admin.client-sheets.create', ['client' => $registered->id]) }}"
                                               class="px-3 py-1.5 rounded-full bg-accent text-dark text-[10px] font-bold uppercase tracking-wider hover:bg-accent-dark transition-all flex items-center gap-1.5 whitespace-nowrap">
                                                <i data-lucide="file-plus" class="w-3.5 h-3.5"></i>
                                                Create client sheet
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
@else
    <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
            <h3 class="font-heading text-base font-bold text-dark">
                {{ $flaggedOnly ? 'Flagged: expired, with penalty or needing attention' : 'Recently added clients' }}
            </h3>

            @if ($flaggedOnly)
                <a href="{{ route('admin.client-sheets.index') }}"
                   class="px-4 py-2 bg-gray-100 text-dark text-[11px] font-bold rounded-full hover:bg-gray-200 transition-all shrink-0">
                    Show all clients
                </a>
            @elseif ($flaggedCount > 0)
                <a href="{{ route('admin.client-sheets.index', ['flagged' => 1]) }}"
                   class="inline-flex items-center gap-2 px-4 py-2 bg-rose-100 text-rose-800 border border-rose-300 text-[11px] font-bold rounded-full hover:bg-rose-200 transition-all shrink-0">
                    <i data-lucide="alert-octagon" class="w-3.5 h-3.5"></i>
                    {{ $flaggedCount }} flagged
                </a>
            @endif
        </div>
        @include('admin.client-sheets.results', ['clients' => $recentClients])
    </div>
@endif
