@extends('layouts.admin')

@section('title', 'Inquiries Management - AMEGA Admin')
@section('page_title', 'Customer Inquiries')

@section('content')
<div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-200 shadow-card space-y-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h2 class="font-heading text-xl font-bold text-dark">Received Inquiries</h2>
            <p class="text-xs text-dark/50">Manage contact & service inquiries submitted by website visitors</p>
        </div>
        <form method="GET" action="{{ route('admin.inquiries.index') }}">
            <x-date-range label="Received" inputClass="py-2 px-3 rounded-xl bg-gray-50 border border-gray-200 text-xs text-dark focus:outline-none focus:ring-2 focus:ring-primary" buttonClass="py-2 px-3 rounded-xl bg-navy text-white text-xs font-bold hover:bg-primary" />
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead>
                <tr class="border-b border-gray-200 text-dark font-extrabold uppercase tracking-wider">
                    <th class="pb-3 px-3">Customer</th>
                    <th class="pb-3 px-3">Service / Request</th>
                    <th class="pb-3 px-3">Message</th>
                    <th class="pb-3 px-3">Follow-up</th>
                    <th class="pb-3 px-3">Submitted</th>
                    <th class="pb-3 px-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($inquiries as $inquiry)
                    @php $isNew = in_array($inquiry->id, $newIds ?? [], true); @endphp
                    <tr class="{{ $isNew ? 'bg-rose-50/40' : '' }} hover:bg-gray-50/50 transition-colors">
                        <td class="py-4 px-3 font-bold text-dark">
                            <div class="flex items-center gap-2">
                                <span>{{ $inquiry->name }}</span>
                                @if ($isNew)
                                    <span class="px-1.5 py-0.5 rounded-full bg-rose-600 text-white text-[9px] font-extrabold uppercase tracking-wider">New</span>
                                @endif
                            </div>
                            <div class="text-[11px] text-dark/50 font-normal">{{ $inquiry->email }}</div>
                            <div class="text-[11px] text-dark/40 font-normal">{{ $inquiry->phone }}{{ $inquiry->phone_apps ? ' · '.$inquiry->phone_apps : '' }}</div>
                            @if ($inquiry->number_of_pax || $inquiry->nationality)
                                <div class="text-[11px] text-dark/50 font-normal">{{ $inquiry->number_of_pax }} pax{{ $inquiry->nationality ? ' · '.$inquiry->nationality : '' }}</div>
                            @endif
                        </td>
                        <td class="py-4 px-3">
                            <span class="px-3 py-1 rounded-full bg-primary/10 text-primary font-bold text-[11px]">
                                {{ $inquiry->service_requested ?: 'General Inquiry' }}
                            </span>
                            @if ($inquiry->service_detail)
                                <div class="mt-1.5 text-[11px] font-semibold text-dark/70 leading-snug max-w-[14rem]">{{ $inquiry->service_detail }}</div>
                            @endif
                        </td>
                        <td class="py-4 px-3 max-w-md text-dark/70 leading-relaxed">
                            {{-- The whole message, with its line breaks, so a long inquiry is never cut off. --}}
                            <p class="whitespace-pre-line break-words">{{ $inquiry->message }}</p>
                        </td>
                        <td class="py-4 px-3 min-w-[15rem]">
                            <form method="POST" action="{{ route('admin.inquiries.update', $inquiry) }}" class="space-y-2">
                                @csrf
                                @method('PATCH')
                                <label class="inline-flex items-center gap-2 font-bold text-dark cursor-pointer">
                                    <input type="checkbox" name="contacted" value="1" @checked($inquiry->contacted_at) class="rounded border-gray-300 text-primary focus:ring-primary">
                                    Client contacted
                                </label>
                                @if ($inquiry->contacted_at)
                                    <div class="text-[11px] text-emerald-700 font-semibold">{{ $inquiry->contacted_at->format('M j, Y g:i A') }}{{ $inquiry->contactedBy ? ' by '.$inquiry->contactedBy->name : '' }}</div>
                                @endif
                                <textarea name="remarks" rows="2" maxlength="2000" placeholder="Remarks"
                                          class="w-full px-3 py-2 rounded-lg bg-gray-50 border border-gray-200 text-xs text-dark focus:outline-none focus:ring-2 focus:ring-primary">{{ $inquiry->remarks }}</textarea>
                                <button type="submit" class="px-3 py-1.5 rounded-lg bg-navy text-white text-[11px] font-bold hover:bg-primary">Save</button>
                            </form>
                        </td>
                        <td class="py-4 px-3 text-dark/50 whitespace-nowrap">
                            {{ $inquiry->created_at ? $inquiry->created_at->format('M j, Y g:i A') : 'N/A' }}
                        </td>
                        <td class="py-4 px-3 text-right">
                            @if (auth()->user()?->isAdmin())
                            <form method="POST" action="{{ route('admin.inquiries.destroy', $inquiry) }}" onsubmit="return confirm('Delete this inquiry?');" class="inline-block">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="p-2 text-rose-500 hover:bg-rose-50 rounded-lg transition-colors" title="Delete Inquiry">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-12 text-center text-dark/40 font-medium">
                            No inquiries found in the database.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pt-4">
        {{ $inquiries->links() }}
    </div>
</div>
@endsection
