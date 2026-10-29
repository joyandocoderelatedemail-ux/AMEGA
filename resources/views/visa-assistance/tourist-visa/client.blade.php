@extends('layouts.visa-assistance')

@section('title', $latest->formal_name.' - Non-Immigrant - AMEGA')

@php
    use App\Models\TouristVisaApplication as TVA;

    $card = 'bg-white rounded-xl border border-slate-200 shadow-sm';
    $th = 'px-3 xl:px-4 py-3 text-xs font-semibold text-slate-500 whitespace-nowrap';
    $primaryButton = 'inline-flex items-center justify-center gap-2 h-10 px-4 rounded-lg bg-navy-700 text-sm font-semibold text-white shadow-sm hover:bg-navy-800 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-500 focus-visible:ring-offset-2';
    $rowButton = 'inline-flex items-center gap-1.5 h-8 px-3 rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:border-slate-300 hover:text-navy-700 transition-colors';

    $date = fn ($value) => $value?->format('M j, Y');
    $address = collect([$latest->address_street, $latest->barangay, $latest->city, $latest->province, $latest->zip_code])->filter()->implode(', ');

    $details = [
        'Date of birth' => $date($latest->date_of_birth),
        'Sex' => ['M' => 'Male', 'F' => 'Female'][$latest->sex] ?? null,
        'Birthplace' => $latest->birthplace,
        'Citizenship' => $latest->citizenship,
        'Civil status' => TVA::CIVIL_STATUSES[$latest->civil_status] ?? null,
        'Height / weight' => collect([$latest->height_cm ? $latest->height_cm.' cm' : null, $latest->weight_kg ? $latest->weight_kg.' kg' : null])->filter()->implode(' / '),
        'Passport no.' => $latest->passport_number,
        'Passport valid until' => $date($latest->passport_validity),
        'Latest arrival' => $date($latest->latest_arrival_date),
        'Flight / voyage' => $latest->flight_number,
        'Mobile' => $latest->mobile_number,
        'Email' => $latest->email,
        'Address in the Philippines' => $address,
        'ACR I-Card' => collect([$latest->acr_number, $latest->acr_validity ? 'valid until '.$date($latest->acr_validity) : null])->filter()->implode(' · '),
    ];
@endphp

@section('content')
<div class="space-y-6">

    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
        <div class="min-w-0">
            <a href="{{ route('visa.tourist-visa.index') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-slate-500 hover:text-navy-700">
                <i data-lucide="arrow-left" class="w-4 h-4"></i> Non-Immigrant
            </a>
            <div class="mt-2 flex items-center gap-3">
                <span class="w-11 h-11 rounded-lg bg-navy-50 text-navy-700 flex items-center justify-center shrink-0">
                    <i data-lucide="folder-open" class="w-5 h-5"></i>
                </span>
                <div class="min-w-0">
                    <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight truncate">{{ $latest->formal_name }}</h1>
                    <p class="mt-0.5 text-sm text-slate-500">{{ $applications->count() }} {{ Str::plural('form', $applications->count()) }} &middot; Passport <span class="font-mono">{{ $latest->passport_number }}</span></p>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 self-start lg:self-auto shrink-0">
            @if (auth()->user()?->isAdmin())
            <form method="POST" action="{{ route('visa.tourist-visa.client.destroy', $key) }}" class="m-0"
                  onsubmit="return confirm({{ Js::from('Delete the whole folder of '.$latest->formal_name.'? All '.$applications->count().' '.Str::plural('form', $applications->count()).', their uploaded documents and payments are removed. This cannot be undone.') }})">
                @csrf
                @method('DELETE')
                <button type="submit" class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-lg border border-rose-200 bg-white text-sm font-semibold text-rose-700 hover:bg-rose-50 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                    <span>Delete folder</span>
                </button>
            </form>
            @endif
            <a href="{{ route('visa.tourist-visa.create', ['passport' => $key]) }}" class="{{ $primaryButton }}">
                <i data-lucide="plus" class="w-4 h-4"></i>
                <span>New Application</span>
            </a>
        </div>
    </div>

    <section class="{{ $card }}">
        <div class="px-4 sm:px-5 py-4 border-b border-slate-200">
            <h2 class="text-base font-bold text-slate-900">Client Details</h2>
            <p class="mt-0.5 text-sm text-slate-500">From the latest form, {{ $latest->reference }}.</p>
        </div>
        <dl class="p-4 sm:p-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4">
            @foreach ($details as $label => $value)
                <div class="min-w-0 {{ in_array($label, ['Address in the Philippines'], true) ? 'sm:col-span-2' : '' }}">
                    <dt class="text-xs font-semibold text-slate-500">{{ $label }}</dt>
                    <dd class="mt-0.5 text-sm text-slate-900 break-words">{{ filled($value) ? $value : '—' }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    <section class="{{ $card }} overflow-hidden">
        <div class="px-4 sm:px-5 py-4 border-b border-slate-200">
            <h2 class="text-base font-bold text-slate-900">Forms</h2>
            <p class="mt-0.5 text-sm text-slate-500">Open a form to review it, or print the application form or the checklist.</p>
        </div>

        <div class="relative overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th scope="col" class="{{ $th }} pl-4 sm:pl-5 xl:pl-5">Reference</th>
                        <th scope="col" class="{{ $th }}">Extension</th>
                        <th scope="col" class="{{ $th }} hidden md:table-cell">Filed</th>
                        <th scope="col" class="{{ $th }} hidden lg:table-cell">Processing</th>
                        <th scope="col" class="{{ $th }}">Payment</th>
                        <th scope="col" class="{{ $th }}">Checklist</th>
                        <th scope="col" class="{{ $th }} pr-4 sm:pr-5 xl:pr-5 text-right"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($applications as $application)
                        @php $missing = count($application->missingChecklist()); @endphp
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="pl-4 sm:pl-5 pr-3 xl:pr-4 py-4">
                                <span class="font-mono text-xs font-semibold text-slate-900 whitespace-nowrap">{{ $application->reference }}</span>
                            </td>
                            <td class="px-3 xl:px-4 py-4 whitespace-nowrap">
                                <div class="text-slate-700">{{ TVA::MONTHS[$application->months_requested] ?? '—' }}</div>
                                <div class="mt-0.5 text-xs text-slate-500">{{ TVA::MODES[$application->application_mode] ?? '' }}</div>
                            </td>
                            <td class="px-3 xl:px-4 py-4 hidden md:table-cell whitespace-nowrap">
                                <div class="text-slate-700">{{ $date($application->filed_on) ?? '—' }}</div>
                                @if ($application->filed_time)
                                    <div class="mt-0.5 text-xs text-slate-500">{{ \Illuminate\Support\Carbon::createFromFormat('H:i', $application->filed_time)->format('g:i A') }}</div>
                                @endif
                            </td>
                            <td class="px-3 xl:px-4 py-4 hidden lg:table-cell text-slate-700">{{ $application->processing_time ?: '—' }}</td>
                            <td class="px-3 xl:px-4 py-4 whitespace-nowrap">
                                @php
                                    $payStatus = $application->paymentStatus();
                                    $payTone = ['paid' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20', 'partial' => 'bg-amber-50 text-amber-800 ring-amber-600/20', 'unpaid' => 'bg-amber-50 text-amber-800 ring-amber-600/20', 'none' => 'bg-slate-100 text-slate-600 ring-slate-500/20'][$payStatus];
                                @endphp
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $payTone }}">{{ ['paid' => 'Paid', 'partial' => 'Partly paid', 'unpaid' => 'Unpaid', 'none' => 'No fee set'][$payStatus] }}</span>
                                @if ($payStatus !== 'none')
                                    <div class="mt-0.5 text-xs text-slate-500 tabular-nums">₱{{ number_format($application->amountPaid(), 2) }} of ₱{{ number_format((float) $application->total_fee, 2) }}@if ($application->payment_type === 'terms' && $application->terms_count) &middot; {{ $application->terms_count }} terms @endif</div>
                                @endif
                            </td>
                            <td class="px-3 xl:px-4 py-4">
                                @if ($missing === 0)
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold whitespace-nowrap ring-1 ring-inset bg-emerald-50 text-emerald-700 ring-emerald-600/20">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500" aria-hidden="true"></span> Complete
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold whitespace-nowrap ring-1 ring-inset bg-amber-50 text-amber-800 ring-amber-600/20">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500" aria-hidden="true"></span> {{ $missing }} missing
                                    </span>
                                @endif
                            </td>
                            <td class="pl-3 xl:pl-4 pr-4 sm:pr-5 py-4">
                                <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                                    <a href="{{ route('visa.tourist-visa.print-form', $application) }}" target="_blank" rel="noopener" class="{{ $rowButton }}" title="Print the application form">
                                        <i data-lucide="printer" class="w-3.5 h-3.5"></i> Form
                                    </a>
                                    <a href="{{ route('visa.tourist-visa.print-checklist', $application) }}" target="_blank" rel="noopener" class="{{ $rowButton }}" title="Print the checklist and claim slip">
                                        <i data-lucide="list-checks" class="w-3.5 h-3.5"></i> Checklist
                                    </a>
                                    <a href="{{ route('visa.tourist-visa.edit', $application) }}" class="{{ $rowButton }}">Open</a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
