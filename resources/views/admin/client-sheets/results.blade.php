@if ($clients->isEmpty())
    <div class="py-12 text-center">
        <i data-lucide="users" class="w-9 h-9 text-dark/25 mx-auto mb-2"></i>
        <p class="text-xs text-dark/50">No client records yet. They appear here as you add them.</p>
    </div>
@else
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead>
                <tr class="border-b border-gray-200 text-dark font-extrabold uppercase tracking-wider">
                    <th class="pb-3 px-3">Client</th>
                    <th class="pb-3 px-3">Passport</th>
                    <th class="pb-3 px-3">Nationality</th>
                    <th class="pb-3 px-3">Extensions</th>
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
                            <span class="px-2.5 py-0.5 rounded-full bg-primary/10 text-primary text-[10px] font-bold whitespace-nowrap">
                                {{ $client->extensions()->count() }} on ledger
                            </span>
                        </td>
                        <td class="py-4 px-3">
                            <div class="flex items-center justify-end gap-1.5">
                                <a href="{{ route('admin.client-sheets.print', $client) }}" target="_blank"
                                   class="px-3 py-1.5 rounded-full bg-primary text-white text-[10px] font-bold uppercase tracking-wider hover:bg-primary-dark transition-all flex items-center gap-1.5">
                                    <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                                    Print sheet
                                </a>
                                <a href="{{ route('admin.client-sheets.edit', $client) }}"
                                   class="p-1.5 text-primary hover:bg-primary/5 rounded-lg flex items-center justify-center transition-colors" title="Edit record">
                                    <i data-lucide="edit" class="w-4 h-4"></i>
                                </a>
                                <form method="POST" action="{{ route('admin.client-sheets.destroy', $client) }}"
                                      onsubmit="return confirm('Delete this client record and their whole extension ledger?');" class="inline-flex m-0">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1.5 text-rose-500 hover:bg-rose-50 rounded-lg flex items-center justify-center transition-colors" title="Delete record">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
