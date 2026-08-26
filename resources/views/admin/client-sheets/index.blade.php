@extends('layouts.immigration')

@section('title', 'Client Sheets - AMEGA Admin')
@section('page_title', 'Client Sheets')

@section('content')
<div class="space-y-6">

    <!-- Passport Lookup -->
    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm">
        <div class="max-w-xl">
            <h2 class="font-heading text-xl font-bold text-dark">Look up a client by passport</h2>
            <p class="text-xs text-dark/50 mt-1">Ask for the passport, type the number, and the client's sheet comes back filled in.</p>

            <form method="GET" action="{{ route('admin.client-sheets.index') }}" class="mt-5 flex flex-col sm:flex-row gap-3">
                <div class="flex-1 relative">
                    <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-dark/40">
                        <i data-lucide="search" class="w-5 h-5"></i>
                    </span>
                    <input type="text" name="passport" value="{{ $passportNumber }}" autofocus
                           placeholder="Passport number"
                           class="w-full pl-12 pr-4 py-3.5 rounded-2xl bg-gray-50 border border-gray-200 text-dark text-base font-mono tracking-wider focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                <button type="submit" class="px-7 py-3.5 bg-primary text-white text-sm font-bold rounded-2xl hover:bg-primary-dark transition-all shadow-md">
                    Search
                </button>
            </form>

            <div class="flex flex-wrap items-center gap-4 mt-4">
                <a href="{{ route('admin.client-sheets.blank') }}" target="_blank" class="inline-flex items-center gap-2 text-xs font-bold text-primary hover:underline">
                    <i data-lucide="printer" class="w-4 h-4"></i>
                    Print a blank form
                </a>
                <a href="{{ route('admin.client-sheets.create') }}" class="inline-flex items-center gap-2 text-xs font-bold text-dark/60 hover:text-dark">
                    <i data-lucide="user-plus" class="w-4 h-4"></i>
                    Add a client without searching
                </a>
            </div>
        </div>
    </div>

    @if ($searched)
        @if ($matches->isEmpty())
            <!-- No match: the new-client path -->
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-amber-200 shadow-sm">
                <div class="flex items-start gap-4">
                    <div class="w-11 h-11 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                        <i data-lucide="user-x" class="w-5 h-5"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-heading text-lg font-bold text-dark">No client on file for “{{ $passportNumber }}”</h3>
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
                            <a href="{{ route('admin.client-sheets.create', ['passport' => $passportNumber]) }}"
                               class="px-5 py-2.5 bg-accent text-dark font-bold text-xs rounded-full hover:bg-accent-dark transition-all shadow-md flex items-center gap-2">
                                <i data-lucide="user-plus" class="w-4 h-4"></i>
                                Create client record
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm">
                <h3 class="font-heading text-base font-bold text-dark mb-4">
                    {{ $matches->count() }} {{ Str::plural('match', $matches->count()) }} for “{{ $passportNumber }}”
                </h3>
                @include('admin.client-sheets.results', ['clients' => $matches])
            </div>
        @endif
    @else
        <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                <h3 class="font-heading text-base font-bold text-dark">
                    {{ $flaggedOnly ? 'Expired or with penalty' : 'Recently added clients' }}
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
                        {{ $flaggedCount }} expired or with penalty
                    </a>
                @endif
            </div>
            @include('admin.client-sheets.results', ['clients' => $recentClients])
        </div>
    @endif

</div>
@endsection
