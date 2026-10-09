{{--
    Flight changes: record a delay, a new date or a cancellation by the airline, see the history, and tell the client.
    Working controls, so they stay off the printed voucher. Needs $ticket, $flightChanges and $smsEnabled.
--}}
@php
    use App\Models\TicketFlightChange;
    use App\Services\ClientNotifier;
    use App\Support\FlightChangeMessage;

    $current = TicketFlightChange::snapshot($ticket);
    $canEmail = ClientNotifier::canReceive($ticket->contact_email);
    $submitted = old('type') !== null;
    $field = 'w-full px-3 py-2 rounded-lg bg-white border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-navy-500 focus:border-navy-500';
    $label = 'block text-xs font-semibold text-slate-600 mb-1';
@endphp

@unless ($ticket->isQuotation())
    <section id="flight-changes" class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 sm:p-6 space-y-4 print:hidden scroll-mt-24"
             x-data="{ open: {{ $submitted ? 'true' : 'false' }}, type: @js(old('type', 'delay')) }">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-heading text-base font-bold text-slate-900">Flight Changes</h2>
                <p class="text-xs text-slate-500 mt-0.5">When the airline delays, moves or cancels the flight, record it here: the booking is updated, the history is kept and the client is told.</p>
            </div>
            @unless ($ticket->isCancelled())
                <button type="button" x-show="!open" @click="open = true"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg bg-navy-700 text-white text-xs font-bold hover:bg-navy-800 transition-colors">
                    <i data-lucide="plane" class="w-4 h-4"></i>
                    Record flight change
                </button>
            @endunless
        </div>

        @if ($ticket->agreementOutdated())
            <div class="flex items-start gap-2.5 p-3 rounded-lg bg-amber-50 ring-1 ring-amber-200">
                <i data-lucide="file-warning" class="w-4 h-4 text-amber-600 shrink-0 mt-0.5"></i>
                <p class="text-xs text-amber-800">
                    The flight changed after the Booking Agreement was made, so the agreement still shows the old schedule.
                    <a href="{{ route('ticketing.agreements.edit', $ticket->bookingAgreement) }}" class="font-bold underline">Update the agreement</a>
                    and send the client a fresh copy.
                </p>
            </div>
        @endif

        @unless ($ticket->isCancelled())
            <form x-show="open" x-cloak method="POST" action="{{ route('ticketing.tickets.flight-changes.store', $ticket) }}"
                  class="rounded-xl border border-slate-200 bg-slate-50/60 p-4 sm:p-5 space-y-4">
                @csrf

                @if ($submitted && $errors->any())
                    <div role="alert" class="p-3 rounded-lg bg-rose-50 ring-1 ring-rose-200 text-xs text-rose-800 space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                <div>
                    <label for="flight_change_type" class="{{ $label }}">What happened</label>
                    <select id="flight_change_type" name="type" x-model="type" class="{{ $field }}">
                        @foreach (TicketFlightChange::TYPES as $value => $typeLabel)
                            <option value="{{ $value }}">{{ $typeLabel }}</option>
                        @endforeach
                    </select>
                </div>

                <div x-show="type !== 'airline_cancelled'" class="space-y-3">
                    <p class="text-xs text-slate-500">Enter what changed. A field left as it is stays as it is.</p>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div class="col-span-2 sm:col-span-1">
                            <label for="fc_departure_date" class="{{ $label }}">Departure date</label>
                            <input id="fc_departure_date" type="date" name="departure_date" value="{{ old('departure_date', $current['departure_date']) }}" class="{{ $field }}">
                        </div>
                        <div>
                            <label for="fc_flight_number" class="{{ $label }}">Flight no.</label>
                            <input id="fc_flight_number" type="text" name="flight_number" maxlength="20" value="{{ old('flight_number', $current['flight_number']) }}" class="{{ $field }} font-mono uppercase" placeholder="5J 5054">
                        </div>
                        <div>
                            <label for="fc_departure_time" class="{{ $label }}">Departs</label>
                            <input id="fc_departure_time" type="time" name="departure_time" value="{{ old('departure_time', $current['departure_time']) }}" class="{{ $field }}">
                        </div>
                        <div>
                            <label for="fc_arrival_time" class="{{ $label }}">Arrives</label>
                            <input id="fc_arrival_time" type="time" name="arrival_time" value="{{ old('arrival_time', $current['arrival_time']) }}" class="{{ $field }}">
                        </div>
                    </div>

                    @if ($ticket->trip_type === 'round_trip')
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div class="col-span-2 sm:col-span-1">
                                <label for="fc_return_date" class="{{ $label }}">Return date</label>
                                <input id="fc_return_date" type="date" name="return_date" value="{{ old('return_date', $current['return_date']) }}" class="{{ $field }}">
                            </div>
                            <div>
                                <label for="fc_return_flight_number" class="{{ $label }}">Return flight no.</label>
                                <input id="fc_return_flight_number" type="text" name="return_flight_number" maxlength="20" value="{{ old('return_flight_number', $current['return_flight_number']) }}" class="{{ $field }} font-mono uppercase">
                            </div>
                            <div>
                                <label for="fc_return_departure_time" class="{{ $label }}">Return departs</label>
                                <input id="fc_return_departure_time" type="time" name="return_departure_time" value="{{ old('return_departure_time', $current['return_departure_time']) }}" class="{{ $field }}">
                            </div>
                            <div>
                                <label for="fc_return_arrival_time" class="{{ $label }}">Return arrives</label>
                                <input id="fc_return_arrival_time" type="time" name="return_arrival_time" value="{{ old('return_arrival_time', $current['return_arrival_time']) }}" class="{{ $field }}">
                            </div>
                        </div>
                    @endif
                </div>

                <p x-show="type === 'airline_cancelled'" x-cloak class="text-xs text-slate-600">
                    The booking's dates stay as they are, so you can rebook or cancel it from here. The client is told the airline cancelled the flight.
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="sm:col-span-2">
                        <label for="fc_reason" class="{{ $label }}">Reason <span class="font-normal text-slate-400">(optional, shown to the client)</span></label>
                        <input id="fc_reason" type="text" name="reason" maxlength="500" value="{{ old('reason') }}" class="{{ $field }}" placeholder="e.g. Aircraft change, weather, schedule adjustment">
                    </div>
                    <div x-show="type !== 'airline_cancelled'">
                        <label for="fc_fee" class="{{ $label }}">Change fee (₱) <span class="font-normal text-slate-400">(optional)</span></label>
                        <input id="fc_fee" type="number" step="0.01" min="0" name="change_fee" value="{{ old('change_fee') }}" class="{{ $field }} font-mono" placeholder="0.00">
                        <p class="text-[11px] text-slate-500 mt-1">
                            @if ($ticket->isIssued())
                                Issued ticket: the fee is quoted to the client but not added to the balance.
                            @else
                                Added to the booking's total.
                            @endif
                        </p>
                    </div>
                </div>

                <fieldset class="space-y-2">
                    <legend class="{{ $label }}">Tell the client</legend>
                    <label class="flex items-start gap-2 text-sm {{ $canEmail ? 'text-slate-800 cursor-pointer' : 'text-slate-400' }}">
                        <input type="checkbox" name="notify_email" value="1" class="mt-0.5 rounded border-slate-300 text-navy-700 focus:ring-navy-500"
                               @checked(old('notify_email', $submitted ? false : $canEmail)) @disabled(! $canEmail)>
                        <span>Email {{ $canEmail ? $ticket->contact_email : '(no email address on file)' }}</span>
                    </label>
                    <label class="flex items-start gap-2 text-sm {{ $smsEnabled ? 'text-slate-800 cursor-pointer' : 'text-slate-400' }}">
                        <input type="checkbox" name="notify_sms" value="1" class="mt-0.5 rounded border-slate-300 text-navy-700 focus:ring-navy-500"
                               @checked(old('notify_sms', $submitted ? false : $smsEnabled)) @disabled(! $smsEnabled)>
                        <span>Text message {{ $ticket->contact_phone ? 'to '.$ticket->contact_phone : '' }} {{ $smsEnabled ? '' : '(SMS is not set up yet)' }}</span>
                    </label>
                    <p class="text-[11px] text-slate-500">Either way, a message is ready to copy afterwards for Viber, Messenger or a call.</p>
                </fieldset>

                <div class="flex items-center gap-2">
                    <button type="submit" class="px-4 py-2 rounded-lg bg-navy-700 text-white text-sm font-semibold hover:bg-navy-800 transition-colors">Save flight change</button>
                    <button type="button" @click="open = false" class="px-4 py-2 rounded-lg bg-white border border-slate-300 text-slate-700 text-sm font-semibold hover:bg-slate-50 transition-colors">Cancel</button>
                </div>
            </form>
        @endunless

        {{-- History, newest first --}}
        @forelse ($flightChanges as $change)
            @php $message = FlightChangeMessage::text($change); @endphp
            <article class="rounded-xl border border-slate-200 p-4 space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold ring-1 {{ $change->isAirlineCancellation() ? 'bg-rose-50 text-rose-700 ring-rose-200' : 'bg-slate-50 text-slate-700 ring-slate-200' }}">{{ $change->label() }}</span>
                        <span class="text-xs text-slate-500">
                            {{ $change->created_at->format('M j, Y g:ia') }}@if ($change->recordedBy) by {{ $change->recordedBy->name }}@endif
                        </span>
                    </div>

                    @if ($change->clientIsNotified())
                        <span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700">
                            <i data-lucide="check-circle-2" class="w-3.5 h-3.5"></i>
                            Client told {{ $change->client_notified_at->format('M j, g:ia') }}{{ $change->notified_via ? ' ('.str_replace(',', ', ', $change->notified_via).')' : '' }}
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 text-xs font-semibold text-amber-700">
                            <i data-lucide="bell-ring" class="w-3.5 h-3.5"></i>
                            Client not told yet
                        </span>
                    @endif
                </div>

                @if ($movements = $change->movements())
                    <ul class="text-sm text-slate-800 space-y-0.5">
                        @foreach ($movements as $move)
                            <li>
                                <span class="text-slate-500">{{ $move['label'] }}:</span>
                                <span class="text-slate-400 line-through">{{ $move['from'] }}</span>
                                <i data-lucide="arrow-right" class="w-3 h-3 inline text-slate-400"></i>
                                <span class="font-semibold">{{ $move['to'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @elseif ($change->isAirlineCancellation())
                    <p class="text-sm text-slate-700">The airline cancelled this flight. Rebook the client, or cancel the booking and refund.</p>
                @endif

                @if ($change->reason)
                    <p class="text-sm text-slate-600"><span class="text-slate-500">Reason:</span> {{ $change->reason }}</p>
                @endif
                @if ((float) $change->change_fee > 0)
                    <p class="text-sm text-slate-600">
                        <span class="text-slate-500">Change fee:</span> <span class="font-mono font-semibold">₱{{ number_format((float) $change->change_fee, 2) }}</span>
                        <span class="text-xs text-slate-500">{{ $change->fee_added ? '(added to the booking total)' : '(not added to the balance)' }}</span>
                    </p>
                @endif

                <div x-data="{ show: false, copied: false }" class="space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" @click="show = !show"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white border border-slate-300 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                            <i data-lucide="message-square" class="w-3.5 h-3.5"></i>
                            <span x-text="show ? 'Hide message' : 'Message for the client'"></span>
                        </button>

                        @unless ($ticket->isCancelled())
                            @foreach ([
                                'email' => [$canEmail, 'Email now', 'mail'],
                                'sms' => [$smsEnabled, 'Send SMS', 'smartphone'],
                            ] as $channel => [$available, $buttonLabel, $icon])
                                @if ($available)
                                    <form method="POST" action="{{ route('ticketing.tickets.flight-changes.notify', [$ticket, $change]) }}" class="m-0">
                                        @csrf
                                        <input type="hidden" name="channel" value="{{ $channel }}">
                                        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white border border-slate-300 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                                            <i data-lucide="{{ $icon }}" class="w-3.5 h-3.5"></i>
                                            {{ $change->clientIsNotified() ? 'Resend: '.strtolower($buttonLabel) : $buttonLabel }}
                                        </button>
                                    </form>
                                @endif
                            @endforeach

                            @unless ($change->clientIsNotified())
                                <form method="POST" action="{{ route('ticketing.tickets.flight-changes.notify', [$ticket, $change]) }}" class="m-0">
                                    @csrf
                                    <input type="hidden" name="channel" value="manual">
                                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700 transition-colors">
                                        <i data-lucide="check" class="w-3.5 h-3.5"></i>
                                        Mark as told
                                    </button>
                                </form>
                            @endunless
                        @endunless
                    </div>

                    <div x-show="show" x-cloak class="space-y-2">
                        <textarea x-ref="msg" readonly rows="{{ min(10, substr_count($message, "\n") + 2) }}"
                                  class="w-full px-3 py-2 rounded-lg bg-slate-50 border border-slate-200 text-sm text-slate-800 font-sans">{{ $message }}</textarea>
                        <button type="button"
                                @click="navigator.clipboard.writeText($refs.msg.value).then(() => { copied = true; setTimeout(() => copied = false, 2000); }).catch(() => $refs.msg.select())"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-navy-700 text-white text-xs font-semibold hover:bg-navy-800 transition-colors">
                            <i data-lucide="copy" class="w-3.5 h-3.5"></i>
                            <span x-text="copied ? 'Copied' : 'Copy message'"></span>
                        </button>
                        <span class="text-[11px] text-slate-500">Paste it into Viber, Messenger or a text, then press Mark as told.</span>
                    </div>
                </div>
            </article>
        @empty
            <p class="text-sm text-slate-500">No flight changes recorded for this booking.</p>
        @endforelse
    </section>
@endunless
