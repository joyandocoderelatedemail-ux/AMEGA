@php
    /**
     * Non-Immigrant files only. Requests for more documents belong to drafting and
     * the embassy stages (open ones block the end of drafting). Insurance and
     * e-Travel are step 8: offered once the file is released, and they never hold it.
     */
    $field = 'w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary';
    $label = 'block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1';
    $button = 'inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-primary text-white font-bold text-xs rounded-xl hover:bg-primary-light transition-all';
    $secondary = 'inline-flex items-center justify-center gap-2 px-3 py-1.5 bg-white border border-gray-200 text-dark/70 font-bold text-[11px] rounded-xl hover:border-gray-300 transition-all';
    $showRequests = in_array($application->status, ['drafting', 'lodged', 'result'], true);
    $showAddons = in_array($application->status, ['result', 'released'], true);
    $requests = $application->additional_requests ?? [];
@endphp

@if ($showRequests || $showAddons)
<div class="grid grid-cols-1 gap-5 mb-6">

    @if ($showRequests)
    <!-- Additional documents: what the embassy or portal asked for after the first submission -->
    <div id="additional-documents" class="scroll-mt-32 rounded-2xl bg-white border border-gray-100 shadow-sm p-6">
        <h2 class="font-heading text-sm font-extrabold text-dark mb-1">Additional Documents</h2>
        <p class="text-xs text-dark/50 mb-4">Request more documents from the client. Open requests hold the file at Drafting until they are received.</p>

        @if ($requests !== [])
            <ul class="divide-y divide-gray-100 rounded-xl border border-gray-100 mb-4">
                @foreach ($requests as $index => $request)
                    <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5">
                        <div class="min-w-0">
                            <p class="text-xs font-bold text-dark">{{ $request['label'] }}</p>
                            <p class="text-[11px] text-dark/50">Requested {{ \Illuminate\Support\Carbon::parse($request['requested_at'])->format('M d, Y') }}</p>
                        </div>
                        @if (! empty($request['received_at']))
                            <span class="text-[11px] font-bold text-emerald-700">Received {{ \Illuminate\Support\Carbon::parse($request['received_at'])->format('M d, Y') }}</span>
                        @else
                            <form method="POST" action="{{ route('visa.applications.requests.received', [$application, $index]) }}" class="m-0">
                                @csrf
                                <button type="submit" class="{{ $secondary }}">Mark received</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('visa.applications.requests.store', $application) }}" class="flex flex-col sm:flex-row gap-3 sm:items-end">
            @csrf
            <div class="flex-1">
                <label for="request_label" class="{{ $label }}">Document needed</label>
                <input id="request_label" type="text" name="label" required maxlength="255" placeholder="e.g. Bank certificate with statements" class="{{ $field }}">
                @error('label') <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="{{ $button }}">Request</button>
        </form>
    </div>

    @endif

    @if ($showAddons)
    <!-- Step 8: insurance and e-Travel, if the client wants them -->
    <div id="addons" class="scroll-mt-32 rounded-2xl bg-white border border-gray-100 shadow-sm p-6">
        <h2 class="font-heading text-sm font-extrabold text-dark mb-1">Insurance &amp; e-Travel</h2>
        <p class="text-xs text-dark/50 mb-4">Only if the client wants them. Nothing here holds the file.</p>

        <form method="POST" action="{{ route('visa.applications.addons', $application) }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
            @csrf
            <input type="hidden" name="section" value="insurance">
            <div>
                <label for="addon_provider" class="{{ $label }}">Insurance Provider</label>
                <input id="addon_provider" type="text" name="insurance_provider" value="{{ old('insurance_provider', $application->insurance_provider) }}" class="{{ $field }}">
            </div>
            <div>
                <label for="addon_policy" class="{{ $label }}">Policy Number</label>
                <input id="addon_policy" type="text" name="insurance_policy_number" value="{{ old('insurance_policy_number', $application->insurance_policy_number) }}" class="{{ $field }}">
            </div>
            <button type="submit" class="{{ $button }}">Save insurance</button>
        </form>
        <div class="mt-2 flex flex-wrap items-center gap-3">
            @if ($application->insurance_declined)
                <span class="text-[11px] font-bold text-emerald-700">Client declined insurance.</span>
            @elseif ($application->insurance_policy_number)
                <span class="text-[11px] font-bold text-emerald-700">{{ $application->insurance_provider }} &middot; {{ $application->insurance_policy_number }}</span>
            @endif
            <form method="POST" action="{{ route('visa.applications.addons', $application) }}" class="m-0">
                @csrf
                <input type="hidden" name="section" value="insurance">
                <input type="hidden" name="insurance_declined" value="1">
                <button type="submit" class="{{ $secondary }}">Client declined insurance</button>
            </form>
        </div>

        <form method="POST" action="{{ route('visa.applications.addons', $application) }}" class="mt-5 pt-5 border-t border-gray-100 flex flex-col sm:flex-row gap-3 sm:items-end">
            @csrf
            <input type="hidden" name="section" value="etravel">
            <div class="flex-1">
                <label for="addon_etravel" class="{{ $label }}">e-Travel Reference</label>
                <input id="addon_etravel" type="text" name="etravel_reference" required value="{{ old('etravel_reference', $application->etravel_reference) }}" class="{{ $field }}">
            </div>
            <button type="submit" class="{{ $button }}">Save reference</button>
        </form>
    </div>
    @endif
</div>
@endif
