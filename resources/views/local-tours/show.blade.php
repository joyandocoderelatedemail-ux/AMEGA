@extends('layouts.local-tours')

@section('title', $booking->reference.' - Local Tours - AMEGA')

{{--
    One local tour booking: where it is on the booking procedure, the work of the current step (tasks to
    tick, details to fill, then Save or Save and continue), and everything on file: the guests, the
    supplier, the money, the driver and the scans.
--}}
@use('App\Models\LocalTourBooking')
@use('App\Models\LocalTourDocument')
@use('App\Support\LocalTours')
@php
    $info = $booking->tourInfo();
    $stages = array_keys(LocalTourBooking::STAGES);
    $total = count($stages);
    $active = $booking->stageIndex();
    $cancelled = $booking->status === 'cancelled';
    $final = $booking->isFinal();
    $stage = (string) $booking->status;
    $tasks = $final ? [] : $booking->tasks();
    $user = auth()->user();
    $canEdit = $user->hasPermission('local_tours.edit');
    // From the supplier booking on, nothing is done while an approver holds the booking.
    $held = in_array($stage, ['supplier', 'voucher', 'transport'], true) ? $booking->approvalBlocker('worked on') : null;
    $locked = $final || $held !== null || ! $canEdit;
    $blocker = $final ? null : $booking->stageBlocker();
    $voucherBlocker = $booking->voucherBlocker();
    $next = $booking->nextStage();
    $balance = $booking->outstandingBalance();
    $guestIds = $booking->documents->where('kind', 'guest_id');

    $control = 'w-full h-10 px-3 rounded-lg bg-white border border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-navy-600 focus:border-navy-600 disabled:bg-slate-100 disabled:text-slate-500';
    $label = 'block text-xs font-semibold text-slate-600 mb-1';
    $card = 'bg-white rounded-xl border border-slate-200 p-5 sm:p-6';
    $term = 'text-xs font-semibold text-slate-500';
    $money = fn ($amount) => '₱'.number_format((float) $amount, 2);
@endphp

@section('content')
<div class="space-y-6" x-data="{ cancelling: false }">
    <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
        <div class="min-w-0">
            <a href="{{ route('local-tours.dashboard') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-900">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                <span>Local tour bookings</span>
            </a>
            <h1 class="mt-2 text-2xl font-bold text-slate-900 font-mono">{{ $booking->reference }}</h1>
            <p class="text-sm text-slate-500">
                {{ $booking->lead_guest_name }} · {{ $booking->tourLabel() }}{{ $booking->travel_date ? ' · '.$booking->travel_date->format('M j, Y') : '' }}
                · Agent: {{ $booking->creator?->name ?? '—' }}
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2 shrink-0">
            @if ($voucherBlocker === null)
                <a href="{{ route('local-tours.bookings.voucher', $booking) }}" target="_blank" rel="noopener" class="h-10 px-4 rounded-lg bg-navy-700 hover:bg-navy-800 text-white text-sm font-semibold inline-flex items-center gap-2">
                    <i data-lucide="printer" class="w-4 h-4"></i> Print voucher
                </a>
            @endif
            @if (! $final && $canEdit)
                <a href="{{ route('local-tours.bookings.edit', $booking) }}" class="h-10 px-4 rounded-lg border border-slate-300 bg-white text-sm font-semibold text-slate-700 hover:bg-slate-50 inline-flex items-center gap-2">
                    <i data-lucide="pencil" class="w-4 h-4"></i> Edit details
                </a>
                <button type="button" @click="cancelling = ! cancelling" :aria-expanded="cancelling.toString()" class="h-10 px-4 rounded-lg border border-slate-300 bg-white text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel booking</button>
            @endif
            @if ($user->isAdmin())
                <form method="POST" action="{{ route('local-tours.bookings.destroy', $booking) }}" class="m-0" onsubmit="return confirm('Delete {{ $booking->reference }} for good? This cannot be undone.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="h-10 px-4 rounded-lg border border-rose-300 bg-white text-sm font-semibold text-rose-700 hover:bg-rose-50">Delete</button>
                </form>
            @endif
        </div>
    </div>

    @if (! $final && $canEdit)
        <form x-show="cancelling" x-cloak method="POST" action="{{ route('local-tours.bookings.cancel', $booking) }}" class="{{ $card }} flex flex-wrap items-end gap-3">
            @csrf
            <div class="flex-1 min-w-[16rem]">
                <label for="cancel-reason" class="{{ $label }}">Why is the booking cancelled? *</label>
                <input id="cancel-reason" type="text" name="reason" required maxlength="500" class="{{ $control }}">
            </div>
            <button type="submit" class="h-10 px-4 rounded-lg border border-rose-300 bg-white text-sm font-semibold text-rose-700 hover:bg-rose-50">Cancel this booking</button>
        </form>
    @endif

    @if ($errors->any())
        <div class="rounded-xl bg-rose-50 border border-rose-200 px-4 py-3" role="alert">
            <ul class="text-sm text-rose-900 space-y-0.5">
                @foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach
            </ul>
        </div>
    @endif

    @if ($booking->isAwaitingApproval())
        <div class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 flex items-start gap-3" role="status">
            <i data-lucide="hourglass" class="w-5 h-5 text-amber-600 shrink-0"></i>
            <p class="text-sm text-amber-900"><strong>Waiting for approval.</strong> An approver is checking the quotation and guest details. Book with the supplier once it is approved.</p>
        </div>
    @elseif ($booking->isReturned())
        <div class="rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 flex items-start gap-3" role="alert">
            <i data-lucide="undo-2" class="w-5 h-5 text-rose-600 shrink-0"></i>
            <p class="text-sm text-rose-900"><strong>Returned by {{ $booking->reviewedBy?->name ?? 'the approver' }}{{ $booking->review_note ? ':' : '.' }}</strong> {{ $booking->review_note }} Correct it under Edit details and save to send it again.</p>
        </div>
    @endif

    <x-file-owner :file="$booking" type="local-tour" />

    {{-- The same stepper as the ticketing wizard: one bar per step, olive for the current one. --}}
    <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div class="min-w-0">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-slate-100 text-xs font-semibold text-slate-600">
                    <i data-lucide="{{ $info['icon'] }}" class="w-3.5 h-3.5"></i> {{ $booking->tourLabel() }}
                </span>
                <p class="mt-2 text-xl font-bold text-slate-900">
                    @if ($cancelled)
                        Cancelled
                    @else
                        Step {{ $active + 1 }} - {{ $booking->status_label }}
                    @endif
                </p>
            </div>
            @unless ($cancelled)
                <div class="flex items-center gap-2 whitespace-nowrap">
                    <span class="text-xs font-semibold text-slate-500">Step</span>
                    <span class="w-8 h-8 rounded-full bg-primary text-white font-bold text-sm flex items-center justify-center">{{ $active + 1 }}</span>
                    <span class="text-xs font-semibold text-slate-500">of {{ $total }}</span>
                </div>
            @endunless
        </div>
        <div class="grid gap-1.5" style="grid-template-columns: repeat({{ $total }}, minmax(0, 1fr))">
            @foreach ($stages as $index => $key)
                @php $isCurrent = ! $cancelled && $index === $active && $stage !== 'completed'; $isDone = ! $cancelled && ($index < $active || $stage === 'completed'); @endphp
                <div class="flex flex-col gap-1.5" @if ($isCurrent) aria-current="step" @endif>
                    <div class="h-2 rounded-full {{ $isCurrent ? 'bg-accent' : ($isDone ? 'bg-primary' : 'bg-gray-200') }}"></div>
                    <span class="text-[9px] font-bold uppercase tracking-wider truncate hidden lg:block {{ $isCurrent ? 'text-slate-900' : ($isDone ? 'text-primary' : 'text-slate-400') }}">{{ LocalTourBooking::STAGES[$key] }}</span>
                </div>
            @endforeach
        </div>
        @if ($cancelled)
            <p class="mt-3 text-sm font-semibold text-rose-700">This booking was cancelled and is off the pipeline.</p>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        <div class="lg:col-span-2 space-y-6">

            {{-- The work of the current step. --}}
            @if ($stage === 'completed')
                <div class="{{ $card }} flex items-start gap-3">
                    <i data-lucide="check-circle" class="w-5 h-5 text-emerald-600 shrink-0"></i>
                    <p class="text-sm text-slate-700"><strong class="text-slate-900">Completed{{ $booking->completed_at ? ' on '.$booking->completed_at->format('M j, Y') : '' }}.</strong> Keep a copy of the booking records for future reference.</p>
                </div>
            @elseif (! $cancelled)
                <form method="POST" action="{{ route('local-tours.bookings.stage', $booking) }}" class="{{ $card }} space-y-5">
                    @csrf
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">{{ $booking->status_label }}</h2>
                        <p class="text-sm text-slate-500">Tick each task as it is done. The booking moves on once they all are.</p>
                    </div>

                    @if ($held)
                        <p class="rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-sm text-amber-900">{{ $held }}</p>
                    @endif

                    <fieldset class="space-y-2" @disabled($locked)>
                        <legend class="sr-only">Tasks at this step</legend>
                        @foreach ($tasks as $key => $task)
                            <label class="flex items-start gap-3 rounded-lg border border-slate-200 px-3 py-2.5 cursor-pointer hover:bg-slate-50">
                                <input type="checkbox" name="tasks[]" value="{{ $key }}" @checked(in_array($key, old('tasks', $booking->checklist ?? []), true)) class="w-4 h-4 mt-0.5 rounded border-slate-300">
                                <span class="text-sm text-slate-800">{{ $task }}</span>
                            </label>
                        @endforeach
                    </fieldset>

                    @if ($stage === 'availability')
                        <fieldset class="grid grid-cols-1 sm:grid-cols-2 gap-4" @disabled($locked)>
                            <div>
                                <label for="total_amount" class="{{ $label }}">Amount quoted to the client (₱) *</label>
                                <input id="total_amount" type="number" name="total_amount" min="0" step="0.01" value="{{ old('total_amount', (float) $booking->total_amount > 0 ? number_format((float) $booking->total_amount, 2, '.', '') : '') }}" class="{{ $control }}">
                            </div>
                            <div>
                                <label for="supplier_cost" class="{{ $label }}">{{ $booking->tour === 'angeles_city' ? "Guide's professional fee (₱)" : "Supplier's rate (₱)" }}</label>
                                <input id="supplier_cost" type="number" name="supplier_cost" min="0" step="0.01" value="{{ old('supplier_cost', $booking->supplier_cost !== null ? number_format((float) $booking->supplier_cost, 2, '.', '') : '') }}" class="{{ $control }}">
                            </div>
                        </fieldset>
                        @if (! $booking->travel_date)
                            <p class="text-sm text-amber-800">No travel date yet. Add it under Edit details.</p>
                        @endif
                    @elseif ($stage === 'guest_details')
                        <div class="rounded-lg bg-slate-50 border border-slate-200 px-3 py-2.5 text-sm text-slate-700 space-y-1">
                            <p>Fill in the guest details under <a href="{{ route('local-tours.bookings.edit', $booking) }}" class="font-semibold text-navy-700 hover:underline">Edit details</a>, and upload the guests' valid IDs under Documents.</p>
                            <p class="{{ $guestIds->isEmpty() ? 'text-amber-800 font-semibold' : 'text-emerald-700 font-semibold' }}">
                                {{ $guestIds->isEmpty() ? 'No valid ID uploaded yet.' : $guestIds->count().' valid '.($guestIds->count() === 1 ? 'ID' : 'IDs').' on file.' }}
                            </p>
                            <p class="text-xs text-slate-500">Continuing from this step sends the booking to an approver.</p>
                        </div>
                    @elseif ($stage === 'supplier')
                        <fieldset class="grid grid-cols-1 sm:grid-cols-3 gap-4" @disabled($locked)>
                            <div>
                                <label for="supplier_confirmation" class="{{ $label }}">Confirmation / voucher no. *</label>
                                <input id="supplier_confirmation" type="text" name="supplier_confirmation" maxlength="100" value="{{ old('supplier_confirmation', $booking->supplier_confirmation) }}" class="{{ $control }}">
                            </div>
                            <div>
                                <label for="supplier_paid_amount" class="{{ $label }}">Paid to the supplier (₱)</label>
                                <input id="supplier_paid_amount" type="number" name="supplier_paid_amount" min="0" step="0.01" value="{{ old('supplier_paid_amount', $booking->supplier_paid_amount !== null ? number_format((float) $booking->supplier_paid_amount, 2, '.', '') : '') }}" class="{{ $control }}">
                            </div>
                            <div>
                                <label for="supplier_payment_reference" class="{{ $label }}">Payment reference</label>
                                <input id="supplier_payment_reference" type="text" name="supplier_payment_reference" maxlength="100" value="{{ old('supplier_payment_reference', $booking->supplier_payment_reference) }}" class="{{ $control }}">
                            </div>
                        </fieldset>
                        <p class="text-xs text-slate-500">Keep the proof of payment and the supplier's confirmation: upload them under Documents.</p>
                    @elseif ($stage === 'voucher')
                        @if ($voucherBlocker)
                            <p class="rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-sm text-amber-900">{{ $voucherBlocker }}</p>
                        @else
                            <p class="rounded-lg bg-emerald-50 border border-emerald-200 px-3 py-2 text-sm text-emerald-900">Paid in full. <a href="{{ route('local-tours.bookings.voucher', $booking) }}" target="_blank" rel="noopener" class="font-semibold underline">Print the voucher</a>, check it, then release it to the client.</p>
                        @endif
                    @elseif ($stage === 'transport')
                        @if ($booking->usesAmegaTransport())
                            <fieldset class="grid grid-cols-1 sm:grid-cols-3 gap-4" @disabled($locked)>
                                <div>
                                    <label for="driver" class="{{ $label }}">AMEGA driver *</label>
                                    <select id="driver" name="driver" class="{{ $control }}">
                                        <option value="">Choose a driver</option>
                                        @foreach (LocalTours::DRIVERS as $key => [$name, $phone])
                                            <option value="{{ $key }}" @selected(old('driver', $booking->driver) === $key)>{{ $name }} · {{ $phone }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="vehicle" class="{{ $label }}">Vehicle</label>
                                    <input id="vehicle" type="text" name="vehicle" maxlength="100" placeholder="e.g. Van, 12 seats" value="{{ old('vehicle', $booking->vehicle) }}" class="{{ $control }}">
                                </div>
                                <div>
                                    <label for="pickup_time" class="{{ $label }}">Pick-up time</label>
                                    <input id="pickup_time" type="time" name="pickup_time" value="{{ old('pickup_time', $booking->pickup_time) }}" class="{{ $control }}">
                                </div>
                            </fieldset>
                            <p class="rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-900"><strong>Important:</strong> {{ LocalTours::DRIVER_IMPORTANT }}</p>
                        @else
                            <p class="text-sm text-slate-600">Transportation: {{ LocalTours::TRANSPORT[$booking->transport] ?? $booking->transport }}. No AMEGA driver is assigned.</p>
                        @endif
                    @endif

                    @if ($blocker && ! $held)
                        <p class="text-sm text-slate-600"><strong class="text-slate-900">Before the next step:</strong> {{ $blocker }}</p>
                    @endif

                    @unless ($locked)
                        <div class="flex flex-wrap items-center gap-3">
                            <button type="submit" class="h-10 px-4 rounded-lg border border-slate-300 bg-white text-sm font-semibold text-slate-700 hover:bg-slate-50">Save</button>
                            @if ($next)
                                <button type="submit" formaction="{{ route('local-tours.bookings.advance', $booking) }}" class="h-10 px-5 rounded-lg bg-navy-700 hover:bg-navy-800 text-white text-sm font-semibold inline-flex items-center gap-2">
                                    <span>{{ $next === 'completed' ? 'Save and complete the booking' : 'Save and continue to '.LocalTourBooking::STAGES[$next] }}</span>
                                    <i data-lucide="arrow-right" class="w-4 h-4"></i>
                                </button>
                            @endif
                        </div>
                    @endunless
                </form>
            @endif

            <section class="{{ $card }} space-y-4" aria-labelledby="details-heading">
                <h2 id="details-heading" class="text-base font-semibold text-slate-900">Guests and trip</h2>
                <dl class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-4">
                    <div><dt class="{{ $term }}">Lead guest</dt><dd class="text-sm text-slate-900">{{ $booking->lead_guest_name }}</dd></div>
                    <div><dt class="{{ $term }}">Contact number</dt><dd class="text-sm text-slate-900">{{ $booking->contact_phone ?: '—' }}</dd></div>
                    <div><dt class="{{ $term }}">Email</dt><dd class="text-sm text-slate-900 break-words">{{ $booking->contact_email ?: '—' }}</dd></div>
                    <div><dt class="{{ $term }}">Guests</dt><dd class="text-sm text-slate-900">{{ $booking->guestsLabel() }}{{ $booking->children_ages ? ' (ages '.$booking->children_ages.')' : '' }}</dd></div>
                    <div><dt class="{{ $term }}">Nationality</dt><dd class="text-sm text-slate-900">{{ $booking->nationality ?: '—' }}</dd></div>
                    <div><dt class="{{ $term }}">Travel date</dt><dd class="text-sm text-slate-900">{{ $booking->travel_date?->format('D, M j, Y') ?? '—' }}</dd></div>
                    <div><dt class="{{ $term }}">Pick-up</dt><dd class="text-sm text-slate-900">{{ collect([$booking->pickup_location, $booking->pickup_time ? \Illuminate\Support\Carbon::parse($booking->pickup_time)->format('g:i A') : null])->filter()->implode(' · ') ?: '—' }}</dd></div>
                    <div><dt class="{{ $term }}">Drop-off</dt><dd class="text-sm text-slate-900">{{ $booking->dropoff_location ?: '—' }}</dd></div>
                    <div><dt class="{{ $term }}">Transportation</dt><dd class="text-sm text-slate-900">{{ LocalTours::TRANSPORT[$booking->transport] ?? $booking->transport }}</dd></div>
                    @if ($booking->driver)
                        <div><dt class="{{ $term }}">Driver</dt><dd class="text-sm text-slate-900">{{ LocalTours::driverName($booking->driver) }} · {{ LocalTours::driverPhone($booking->driver) }}{{ $booking->vehicle ? ' · '.$booking->vehicle : '' }}</dd></div>
                    @endif
                    @if ($booking->attractions)
                        <div class="col-span-2 sm:col-span-3"><dt class="{{ $term }}">Preferred attractions</dt><dd class="text-sm text-slate-900 whitespace-pre-line">{{ $booking->attractions }}</dd></div>
                    @endif
                    @if ($booking->special_requests)
                        <div class="col-span-2 sm:col-span-3"><dt class="{{ $term }}">Special requests</dt><dd class="text-sm text-slate-900 whitespace-pre-line">{{ $booking->special_requests }}</dd></div>
                    @endif
                    @if ($booking->remarks)
                        <div class="col-span-2 sm:col-span-3"><dt class="{{ $term }}">Remarks</dt><dd class="text-sm text-slate-900 whitespace-pre-line">{{ $booking->remarks }}</dd></div>
                    @endif
                </dl>
            </section>

            <section class="{{ $card }} space-y-4" aria-labelledby="documents-heading">
                <h2 id="documents-heading" class="text-base font-semibold text-slate-900">Documents</h2>
                @if ($booking->documents->isEmpty())
                    <p class="text-sm text-slate-500">Nothing filed yet. Upload the guests' valid IDs, the supplier's confirmation and the proof of payment here.</p>
                @else
                    <ul class="divide-y divide-slate-200">
                        @foreach ($booking->documents as $document)
                            <li class="py-2.5 flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <a href="{{ route('local-tours.documents.download', $document) }}" target="_blank" rel="noopener" class="text-sm font-semibold text-navy-700 hover:underline break-all">{{ $document->original_name }}</a>
                                    <p class="text-xs text-slate-500">{{ $document->kindLabel() }} · {{ $document->created_at?->format('M j, Y g:i A') }}</p>
                                </div>
                                @if ($canEdit && ! $final)
                                    <form method="POST" action="{{ route('local-tours.documents.destroy', $document) }}" class="m-0 shrink-0" onsubmit="return confirm('Remove this file from the booking?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="h-8 px-2.5 rounded-lg border border-slate-300 text-xs font-semibold text-slate-600 hover:bg-slate-50">Remove</button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if ($canEdit && ! $cancelled)
                    <form method="POST" action="{{ route('local-tours.documents.store', $booking) }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-3 pt-1">
                        @csrf
                        <div>
                            <label for="document-kind" class="{{ $label }}">Kind</label>
                            <select id="document-kind" name="kind" class="{{ $control }}">
                                @foreach (LocalTourDocument::KINDS as $key => $name)
                                    <option value="{{ $key }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex-1 min-w-[14rem]">
                            <label for="document-files" class="{{ $label }}">Files (PDF or image, up to 5 MB each)</label>
                            <input id="document-files" type="file" name="files[]" multiple required accept=".pdf,.jpg,.jpeg,.png,.webp" class="block w-full text-sm text-slate-700 file:mr-3 file:h-10 file:px-3 file:rounded-lg file:border file:border-slate-300 file:bg-white file:text-sm file:font-semibold file:text-slate-700">
                        </div>
                        <button type="submit" class="h-10 px-4 rounded-lg border border-slate-300 bg-white text-sm font-semibold text-slate-700 hover:bg-slate-50">Upload</button>
                    </form>
                @endif
            </section>
        </div>

        <div class="space-y-6">
            <section class="{{ $card }} space-y-3" aria-labelledby="supplier-heading">
                <h2 id="supplier-heading" class="text-base font-semibold text-slate-900">{{ $info['contact_label'] }}</h2>
                <div>
                    <p class="text-sm font-semibold text-slate-900">{{ $info['supplier'] }}</p>
                    @if ($info['phone'])
                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $info['phone']) }}" class="text-sm text-navy-700 hover:underline">{{ $info['phone'] }}</a>
                    @endif
                    @if ($info['accreditation'])
                        <p class="text-xs text-slate-500">{{ $info['accreditation'] }}</p>
                    @endif
                </div>
                <dl class="grid grid-cols-2 gap-3">
                    <div><dt class="{{ $term }}">Rate</dt><dd class="text-sm text-slate-900">{{ $booking->supplier_cost !== null ? $money($booking->supplier_cost) : '—' }}</dd></div>
                    <div><dt class="{{ $term }}">Paid to them</dt><dd class="text-sm text-slate-900">{{ $booking->supplier_paid_amount !== null ? $money($booking->supplier_paid_amount) : '—' }}</dd></div>
                    <div class="col-span-2"><dt class="{{ $term }}">Confirmation no.</dt><dd class="text-sm text-slate-900 font-mono">{{ $booking->supplier_confirmation ?: '—' }}</dd></div>
                </dl>
                @if ($info['important'])
                    <p class="rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-900"><strong>Important:</strong> {{ $info['important'] }}</p>
                @endif
            </section>

            <section class="{{ $card }} space-y-3" aria-labelledby="payment-heading">
                <div class="flex items-center justify-between gap-3">
                    <h2 id="payment-heading" class="text-base font-semibold text-slate-900">Client payment</h2>
                    @if ($booking->isFullyPaid())
                        <span class="px-2.5 py-1 rounded-lg bg-emerald-50 border border-emerald-200 text-xs font-semibold text-emerald-700">Paid in full</span>
                    @elseif ((float) $booking->amount_paid > 0)
                        <span class="px-2.5 py-1 rounded-lg bg-amber-50 border border-amber-200 text-xs font-semibold text-amber-700">Partly paid</span>
                    @endif
                </div>
                @if ((float) $booking->total_amount <= 0)
                    <p class="text-sm text-slate-500">No amount quoted yet.</p>
                @else
                    <dl class="grid grid-cols-3 gap-3">
                        <div><dt class="{{ $term }}">Quoted</dt><dd class="text-sm font-semibold text-slate-900">{{ $money($booking->total_amount) }}</dd></div>
                        <div><dt class="{{ $term }}">Paid</dt><dd class="text-sm font-semibold text-emerald-700">{{ $money($booking->amount_paid) }}</dd></div>
                        <div><dt class="{{ $term }}">Balance</dt><dd class="text-sm font-semibold {{ $balance > 0 ? 'text-amber-700' : 'text-slate-400' }}">{{ $money($balance) }}</dd></div>
                    </dl>
                    @if ($booking->payments->isNotEmpty())
                        <ul class="divide-y divide-slate-200 border-t border-slate-200">
                            @foreach ($booking->payments as $entry)
                                <li class="py-2">
                                    <p class="text-sm text-slate-900">{{ $money($entry->amount) }} <span class="text-slate-500">· {{ $entry->methodLabel() }}</span></p>
                                    <p class="text-xs text-slate-500">{{ collect([$entry->reference ? 'OR '.$entry->reference : null, $entry->received_at?->format('M j, Y'), $entry->receivedBy?->name])->filter()->implode(' · ') }}</p>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    <p class="text-xs text-slate-500">
                        @if ($booking->approval_status === LocalTourBooking::APPROVAL_APPROVED && $balance > 0)
                            The cashier records the payment.
                            @if ($user->isAdmin() || $user->isCashier())
                                <a href="{{ route('cashier.local-tours.show', $booking->id) }}" class="font-semibold text-navy-700 hover:underline">Open at the cashier</a>
                            @endif
                        @elseif ($balance > 0)
                            The cashier takes payment once the booking is approved.
                        @endif
                    </p>
                @endif
            </section>
        </div>
    </div>
</div>
@endsection
