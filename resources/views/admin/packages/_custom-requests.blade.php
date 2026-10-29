{{-- The Custom Requests tab of the packages page: each client's tailor-made trip quotation. --}}
    <div class="bg-white rounded-3xl p-6 border border-gray-200 shadow-card space-y-4">
        <div class="flex items-center justify-between border-b border-gray-100 pb-4">
            <div>
                <h2 class="font-heading text-lg font-bold text-dark">Custom Package Quotations &amp; Inquiries</h2>
                <p class="text-xs text-dark/50">List of all tailor-made customer travel packages configured by staff.</p>
            </div>
            <a href="{{ route('admin.packages.configurator') }}" class="px-4 py-2 bg-accent text-dark font-bold text-xs rounded-xl hover:bg-accent-dark transition-all flex items-center gap-1.5 shadow-sm">
                <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                <span>New Custom Quotation</span>
            </a>
        </div>

        @if($recentInquiries->isEmpty())
            <div class="text-center py-12 space-y-3">
                <div class="w-12 h-12 rounded-2xl bg-gray-100 text-dark/40 flex items-center justify-center mx-auto">
                    <i data-lucide="clipboard-x" class="w-6 h-6"></i>
                </div>
                <h4 class="font-heading font-bold text-sm text-dark">No Custom Quotations Yet</h4>
                <p class="text-xs text-dark/50 max-w-sm mx-auto">Click the button below to build your first tailored package quotation with hotel, bed, smoking, pet, and transportation preferences.</p>
                <a href="{{ route('admin.packages.configurator') }}" class="px-5 py-2.5 rounded-xl bg-primary text-white font-bold text-xs hover:bg-navy transition-all">
                    Configure Custom Package
                </a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-gray-200 text-dark font-extrabold uppercase tracking-wider">
                            <th class="pb-3 px-3">Reference #</th>
                            <th class="pb-3 px-3">Client</th>
                            <th class="pb-3 px-3">Destination</th>
                            <th class="pb-3 px-3">Hotel &amp; Room</th>
                            <th class="pb-3 px-3"># Pax</th>
                            <th class="pb-3 px-3">Budget</th>
                            <th class="pb-3 px-3">Status</th>
                            <th class="pb-3 px-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($recentInquiries as $inq)
                            <tr class="hover:bg-gray-50/70 transition-colors">
                                <td class="py-3 px-3 font-mono font-bold text-primary">
                                    <a href="{{ route('admin.packages.custom-inquiries.show', $inq) }}" class="hover:underline">
                                        {{ $inq->reference_number }}
                                    </a>
                                </td>
                                <td class="py-3 px-3">
                                    <div class="font-bold text-dark">{{ $inq->client_name }}</div>
                                    <div class="text-[11px] text-dark/50">{{ $inq->client_email }}</div>
                                </td>
                                <td class="py-3 px-3">
                                    <div class="font-semibold text-dark">{{ $inq->destination_name ?: 'Flexible' }}</div>
                                    <span class="text-[10px] uppercase font-bold text-dark/40">{{ $inq->travel_type }}</span>
                                </td>
                                <td class="py-3 px-3">
                                    <div class="font-semibold text-dark truncate max-w-[150px]">{{ $inq->hotel_name ?: ($inq->preferred_hotel ?: 'Standard') }}</div>
                                    <div class="text-[10px] text-dark/50">{{ $inq->bed_config }} &bull; {{ $inq->has_breakfast ? 'w/ Bfast' : 'No Bfast' }}</div>
                                </td>
                                <td class="py-3 px-3 font-bold text-dark">
                                    {{ $inq->number_of_pax }} Pax
                                </td>
                                <td class="py-3 px-3 font-mono font-bold text-dark">
                                    {{ $inq->formatted_budget }}
                                </td>
                                <td class="py-3 px-3">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border {{ $inq->status_badge_class }}">
                                        {{ $inq->status }}
                                    </span>
                                </td>
                                <td class="py-3 px-3 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a href="{{ route('admin.packages.custom-inquiries.show', $inq) }}"
                                           class="p-1.5 rounded-lg border border-gray-200 text-dark/70 hover:bg-gray-100 hover:text-dark transition-all"
                                           title="View Quotation Card">
                                            <i data-lucide="eye" class="w-4 h-4"></i>
                                        </a>
                                        @if (auth()->user()?->isAdmin())
                                        <form method="POST" action="{{ route('admin.packages.custom-inquiries.destroy', $inq) }}"
                                              onsubmit="return confirm('Delete this custom quotation inquiry?');"
                                              class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1.5 rounded-lg border border-gray-200 text-rose-600 hover:bg-rose-50 transition-all cursor-pointer" title="Delete">
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

            <div class="pt-4 border-t border-gray-100">
                {{ $recentInquiries->links() }}
            </div>
        @endif
    </div>
