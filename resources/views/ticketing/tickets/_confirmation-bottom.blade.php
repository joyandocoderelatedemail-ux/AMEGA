{{-- Part of the booking confirmation (_confirmation.blade.php): Special requests and the booker / issuing officer footer. --}}
        <!-- Special Requests -->
        @if($ticket->special_requests || !empty($ticket->special_requests_list))
            <div class="p-4 rounded-2xl bg-gray-50 border border-gray-200 space-y-2 text-xs">
                <span class="font-bold text-dark uppercase tracking-wider text-[10px] block">Special Requests &amp; Seating Preferences</span>
                @if(!empty($ticket->special_requests_list))
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($ticket->special_requests_list as $req)
                            <span class="px-2 py-0.5 rounded-lg bg-white border text-dark font-bold text-[10px]">
                                • {{ ucwords(str_replace('_', ' ', $req)) }}
                                @if($price = $ticket->extraPriceLabel($req))
                                    <span class="font-mono text-dark/50">· {{ $price }}</span>
                                @endif
                            </span>
                        @endforeach
                    </div>
                @endif
                @if($ticket->special_requests)
                    <p class="text-dark/80 whitespace-pre-line mt-1">{{ $ticket->special_requests }}</p>
                @endif
            </div>
        @endif

        <!-- Contact and Officer Footer -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-6 border-t border-gray-100 text-xs">
            <div class="space-y-1">
                <span class="font-bold text-dark/50 uppercase tracking-wider text-[10px]">Primary Booker / Contact</span>
                <div class="font-bold text-dark">{{ $ticket->contact_name }}</div>
                @if ($ticket->corporateAccount)
                    <div class="text-dark/70 font-semibold">Corporate booking &middot; {{ $ticket->corporateAccount->company_name }}</div>
                @endif
                <div class="text-dark/60">{{ $ticket->contact_email }} • {{ $ticket->contact_phone }}</div>
            </div>
            <div class="sm:text-right space-y-1">
                <span class="font-bold text-dark/50 uppercase tracking-wider text-[10px]">Issuing Officer</span>
                <div class="font-bold text-dark">{{ $ticket->createdBy?->name ?? 'AMEGA Staff' }}</div>
                <div class="text-dark/40">Issued on {{ $ticket->created_at->format('M d, Y h:i A') }}</div>
            </div>
        </div>
