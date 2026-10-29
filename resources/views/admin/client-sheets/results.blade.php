@php
    // Which services each folder holds, as badges: the ledger for Visa Extension, the cases for the rest.
    $serviceBadges = function ($client): array {
        $badges = [];
        if ($client->extensions_count ?? 0) {
            $badges['visa_extension'] = 'Visa Extension · '.$client->extensions_count;
        }
        if ($client->overstays_count ?? 0) {
            $badges['visa_overstaying'] = 'Overstaying'.($client->overstays_count > 1 ? ' · '.$client->overstays_count : '');
        }
        if ($client->restampings_count ?? 0) {
            $badges['re_stamping_amendment'] = 'Re-stamping'.($client->restampings_count > 1 ? ' · '.$client->restampings_count : '');
        }
        $short = ['spouse' => '13/A Spouse', 'permanent' => '13/A Permanent', 'renewal' => '13/A Series', 'dual' => 'Dual Citizenship', 'downgrade' => 'Downgrading', 'ecc' => 'ECC'];
        foreach ($client->relationLoaded('petitions') ? $client->petitions->groupBy('type') : [] as $type => $cases) {
            $key = array_search($type, \App\Models\ImmigrationPetition::SERVICES, true);
            $badges[$key] = ($short[$type] ?? $type).($cases->count() > 1 ? ' · '.$cases->count() : '');
        }

        return $badges;
    };
    $filter = $service ?? null;
@endphp

@if ($clients->isEmpty())
    <div class="py-12 text-center">
        <i data-lucide="folder-open" class="w-9 h-9 text-dark/25 mx-auto mb-2"></i>
        <p class="text-xs text-dark/50">{{ $filter ? 'No client folder has this service yet.' : 'No client folders yet. They appear here as you add them.' }}</p>
    </div>
@else
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead>
                <tr class="border-b border-gray-200 text-dark font-extrabold uppercase tracking-wider">
                    <th class="pb-3 px-3">Client</th>
                    <th class="pb-3 px-3">Passport</th>
                    <th class="pb-3 px-3">Nationality</th>
                    <th class="pb-3 px-3">Services</th>
                    <th class="pb-3 px-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach ($clients as $client)
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="py-4 px-3">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="font-bold text-dark">{{ $client->full_name }}</span>
                                @include('admin.client-sheets.marks', ['client' => $client, 'size' => 'sm'])
                            </div>
                            <div class="text-[11px] text-dark/50">{{ $client->email ?: 'No email on file' }}</div>
                        </td>
                        <td class="py-4 px-3 font-mono font-bold text-primary whitespace-nowrap">
                            {{ $client->passport_number ?: '—' }}
                        </td>
                        <td class="py-4 px-3 text-dark/70">{{ $client->nationality ?: '—' }}</td>
                        <td class="py-4 px-3">
                            <div class="flex flex-wrap gap-1">
                                @forelse ($serviceBadges($client) as $key => $badge)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold whitespace-nowrap {{ $key === $filter ? 'bg-primary text-white' : 'bg-primary/10 text-primary' }}">{{ $badge }}</span>
                                @empty
                                    <span class="text-[11px] text-dark/40">No service yet</span>
                                @endforelse
                            </div>
                        </td>
                        <td class="py-4 px-3">
                            <div class="flex items-center justify-end gap-1.5">
                                {{-- Filtered by a service, the folder opens straight in it. --}}
                                <a href="{{ \App\Support\ImmigrationServices::folderUrl($client, $filter) }}"
                                   class="px-3 py-1.5 rounded-full bg-primary text-white text-[10px] font-bold uppercase tracking-wider hover:bg-primary-dark transition-all flex items-center gap-1.5 whitespace-nowrap">
                                    <i data-lucide="folder-open" class="w-3.5 h-3.5"></i>
                                    Open folder
                                </a>
                                <a href="{{ route('admin.client-sheets.print', $client) }}" target="_blank"
                                   class="p-1.5 text-primary hover:bg-primary/5 rounded-lg flex items-center justify-center transition-colors" title="Print the client information sheet">
                                    <i data-lucide="printer" class="w-4 h-4"></i>
                                </a>
                                @if (auth()->user()?->isAdmin())
                                <form method="POST" action="{{ route('admin.client-sheets.destroy', $client) }}"
                                      onsubmit="return confirm('Delete this client folder, with its extension ledger and every service case in it?');" class="inline-flex m-0">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1.5 text-rose-500 hover:bg-rose-50 rounded-lg flex items-center justify-center transition-colors" title="Delete record">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
