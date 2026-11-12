@php
    /**
     * One of the dashboard's call lists (see App\Support\ImmigrationExpiries): who to phone, how to
     * reach them, when their visa or card runs out, and the service to start. Eight rows show; the
     * rest open with "Show all".
     *
     * @var string $id
     * @var string $title
     * @var string $intro
     * @var string $empty
     * @var \Illuminate\Support\Collection $rows
     * @var ?string $action  the button label when every row has the same one
     */
    $pill = [
        'rose' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        'amber' => 'bg-amber-50 text-amber-800 ring-amber-600/20',
        'slate' => 'bg-slate-100 text-slate-600 ring-slate-500/20',
    ];
    $lapsed = $rows->where('tone', 'rose')->count();
@endphp
<section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden {{ $lapsed ? 'ring-1 ring-rose-200' : '' }}" aria-labelledby="{{ $id }}-heading" x-data="{ all: false }">
    <div class="px-5 py-4 border-b border-slate-200">
        <div class="flex items-center justify-between gap-3">
            <h2 id="{{ $id }}-heading" class="text-base font-semibold text-slate-900">{{ $title }}</h2>
            <span class="text-xs text-slate-500">
                {{ $rows->count() }} to call
                @if ($lapsed)
                    &middot; <span class="font-semibold text-rose-700">{{ $lapsed }} lapsed</span>
                @endif
            </span>
        </div>
        <p class="mt-0.5 text-xs text-slate-500">{{ $intro }}</p>
    </div>

    @if ($rows->isEmpty())
        <p class="px-5 py-10 text-center text-sm text-slate-500">{{ $empty }}</p>
    @else
        <ul class="divide-y divide-slate-100">
            @foreach ($rows as $row)
                @php
                    $folder = $row['folder'];
                    // The folder's number, or the one on the client's profile.
                    $phone = $folder->mobile_number ?: $folder->user?->phone;
                @endphp
                <li class="flex items-center gap-3 px-5 py-3" @if ($loop->index >= 8) x-show="all" style="display: none" @endif>
                    <div class="min-w-0 flex-1">
                        <a href="{{ \App\Support\ImmigrationServices::folderUrl($folder) }}" class="block text-sm font-semibold text-slate-900 truncate hover:underline">{{ $folder->full_name }}</a>
                        <span class="block text-xs text-slate-500 truncate">
                            @if ($phone)
                                <a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="font-semibold text-navy-700 hover:underline">{{ $phone }}</a>
                            @else
                                <span class="text-rose-700">No phone on file</span>
                            @endif
                            {{ collect([$folder->email, isset($row['acr']) && $row['acr'] ? 'ACR '.$row['acr'] : ($folder->passport_number ? 'Passport '.$folder->passport_number : null)])->filter()->map(fn ($part) => ' · '.$part)->implode('') }}
                        </span>
                    </div>
                    <div class="text-right shrink-0">
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset whitespace-nowrap {{ $pill[$row['tone']] }}">{{ $row['when'] }}</span>
                        <span class="block mt-0.5 text-xs text-slate-500 whitespace-nowrap">{{ $row['date']->format('M j, Y') }}</span>
                    </div>
                    <a href="{{ $row['url'] }}" class="shrink-0 inline-flex items-center h-8 px-3 rounded-lg text-xs font-semibold whitespace-nowrap transition-colors {{ $row['tone'] === 'rose' ? 'bg-rose-600 text-white hover:bg-rose-700' : 'bg-navy-700 text-white hover:bg-navy-800' }}">
                        {{ $row['action'] ?? $action }}
                    </a>
                </li>
            @endforeach
        </ul>
        @if ($rows->count() > 8)
            <button type="button" @click="all = ! all" class="w-full px-5 py-2.5 border-t border-slate-100 text-xs font-semibold text-navy-700 hover:bg-slate-50"
                    x-text="all ? 'Show fewer' : 'Show all {{ $rows->count() }}'">Show all {{ $rows->count() }}</button>
        @endif
    @endif
</section>
