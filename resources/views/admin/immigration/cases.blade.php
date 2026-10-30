@extends('layouts.immigration')

@section('title', 'Transactions - AMEGA Admin')
@section('page_title', 'Transactions')

@php
    use App\Support\CaseProgress;
    use App\Support\ImmigrationCases;
    use App\Support\ImmigrationServices;

    $select = 'w-full pl-3 pr-8 py-3 rounded-2xl bg-gray-50 border text-sm font-semibold text-dark focus:outline-none focus:ring-2 focus:ring-primary cursor-pointer';
    $activeFilters = $view !== '' || $status !== 'all' || $search !== '' || $flaggedOnly || ! \App\Support\DateRange::fromRequest(request())->isAllTime();
@endphp

@section('content')
<div class="space-y-6">

    <div class="bg-white rounded-3xl p-5 sm:p-6 border border-gray-200 shadow-card">
        <form method="GET" action="{{ route('admin.immigration.cases') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3" role="search">
            @if ($flaggedOnly) <input type="hidden" name="flagged" value="1"> @endif
            <div class="relative sm:col-span-2 lg:col-span-9">
                <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-dark/40">
                    <i data-lucide="search" class="w-5 h-5"></i>
                </span>
                <input type="search" name="q" value="{{ $search }}" autocomplete="off"
                       placeholder="Client, passport or reference"
                       aria-label="Search transactions by client name, passport or a reference such as OS-2026-0014"
                       class="w-full pl-12 pr-4 py-3 rounded-2xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary">
            </div>

            <div class="sm:col-span-2 lg:col-span-3 flex items-center gap-3">
                <button type="submit" class="flex-1 px-6 py-3 bg-primary text-white text-sm font-bold rounded-2xl hover:bg-primary-dark transition-all shadow-md">Search</button>
                @if ($activeFilters)
                    <a href="{{ route('admin.immigration.cases') }}" class="text-xs font-bold text-dark/60 hover:text-primary whitespace-nowrap">Clear</a>
                @endif
            </div>

            <div class="lg:col-span-4">
                <select name="service" onchange="this.form.requestSubmit()" aria-label="Service" class="{{ $select }} {{ $view !== '' ? 'border-primary' : 'border-gray-200' }}">
                    <option value="">All services</option>
                    @foreach ($kinds as $key => $kindInfo)
                        <option value="{{ $key }}" @selected($view === $key)>{{ $kindInfo['label'] }}{{ $counts[$key] ? ' ('.$counts[$key].' open)' : '' }}</option>
                    @endforeach
                    <option value="visa_extension" @selected($view === 'visa_extension')>Visa Extension ({{ $extensionClientCount }} {{ \Illuminate\Support\Str::plural('client', $extensionClientCount) }})</option>
                    <option value="folders" @selected($view === 'folders')>All client folders ({{ $folderCount }})</option>
                </select>
            </div>
            @unless ($foldersMode)
            <div class="lg:col-span-4">
                <select name="status" onchange="this.form.requestSubmit()" aria-label="Status" class="{{ $select }} {{ $status !== 'all' ? 'border-primary' : 'border-gray-200' }}">
                    @foreach (ImmigrationCases::STATUSES as $key => $statusLabel)
                        <option value="{{ $key }}" @selected($status === $key)>{{ $statusLabel }}</option>
                    @endforeach
                    <optgroup label="Where it stands">
                        @foreach (CaseProgress::OPTIONS as $key => $progressLabel)
                            <option value="{{ $key }}" @selected($status === $key)>{{ $progressLabel }}</option>
                        @endforeach
                    </optgroup>
                </select>
            </div>
            @endunless
            <x-date-range label="Opened" class="sm:col-span-2 lg:col-span-12" inputClass="px-3 py-2.5 rounded-2xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary" buttonClass="px-4 py-2.5 rounded-2xl bg-primary text-white text-sm font-bold hover:bg-primary-dark" />
        </form>
    </div>

    @if ($foldersMode)
        <div id="client-folders" class="space-y-6">
            @include('admin.immigration._folders')
        </div>
    @else

    <div class="bg-white rounded-3xl border border-gray-200 shadow-card overflow-hidden">
        @if ($cases->isEmpty())
            @if ($matches->isEmpty() && $clientsWithoutFolder->isEmpty())
            <div class="p-8 text-center">
                <i data-lucide="folder-open" class="w-8 h-8 text-dark/30 mx-auto"></i>
                <p class="mt-3 text-sm font-bold text-dark">No transactions match</p>
                <p class="text-xs text-dark/50 mt-1">
                    @if ($search) Nothing found for &ldquo;{{ $search }}&rdquo;. Check the spelling or the reference. @else Transactions opened from the Services menu show up here. @endif
                </p>
            </div>
            @else
                <p class="px-6 py-5 text-sm text-dark/60">No transactions for &ldquo;{{ $search }}&rdquo;. Their client folders are below.</p>
            @endif
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="px-5 py-3 text-[10px] font-extrabold uppercase tracking-wider text-dark/50">Reference</th>
                            <th class="px-3 py-3 text-[10px] font-extrabold uppercase tracking-wider text-dark/50">Client</th>
                            <th class="px-3 py-3 text-[10px] font-extrabold uppercase tracking-wider text-dark/50">Service</th>
                            <th class="px-3 py-3 text-[10px] font-extrabold uppercase tracking-wider text-dark/50">Update Status</th>
                            <th class="px-3 py-3 text-[10px] font-extrabold uppercase tracking-wider text-dark/50">Dates</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($cases as $case)
                            <tr class="hover:bg-gray-50" style="vertical-align: top">
                                <td class="px-5 py-3 font-mono text-xs font-bold text-primary whitespace-nowrap">{{ $case['reference'] }}</td>
                                <td class="px-3 py-3">
                                    <span class="block text-xs font-bold text-dark">{{ $case['client']?->full_name ?? 'Unknown client' }}</span>
                                    <span class="block font-mono text-[11px] text-dark/50">{{ $case['client']?->passport_number }}</span>
                                </td>
                                <td class="px-3 py-3">
                                    <span class="block text-xs font-semibold text-dark">{{ $case['label'] }}</span>
                                    <span class="block text-[11px] text-dark/50">{{ $case['summary'] }}</span>
                                </td>
                                <td class="px-3 py-3">
                                    @include('admin.immigration._case-status', ['case' => $case])
                                    @include('admin.immigration._case-approval', ['case' => $case])
                                    @if (! $case['closed'] && $case['missing'])
                                        <span class="block mt-1 text-[11px] font-semibold text-amber-700">{{ $case['missing'] }} {{ \Illuminate\Support\Str::plural('requirement', $case['missing']) }} missing</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-[11px] text-dark/60 whitespace-nowrap">
                                    Opened {{ $case['opened']?->format('M d, Y') }}
                                    @if ($case['filed_on']) <br>Filed {{ $case['filed_on']->format('M d, Y') }} @endif
                                    @if ($case['released_on']) <br>{{ CaseProgress::closes($case['progress']) ? 'Closed' : 'Released' }} {{ $case['released_on']->format('M d, Y') }} @endif
                                </td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    @include('admin.immigration._case-release', ['case' => $case])
                                    @if ($case['url'])
                                        <a href="{{ $case['url'] }}" class="ml-3 inline-flex items-center gap-1 text-xs font-bold text-primary hover:underline whitespace-nowrap">Open <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i></a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if ($search !== '' && $view === '')
        <div class="space-y-6">
            @include('admin.immigration._folders')
        </div>
    @endif
    @endif
</div>
@endsection
