@extends('layouts.admin')

@section('title', 'Inquiries Management - AMEGA Admin')
@section('page_title', 'Customer Inquiries')

@section('content')
<div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm space-y-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h2 class="font-heading text-xl font-bold text-dark">Received Inquiries</h2>
            <p class="text-xs text-dark/50">Manage contact & service inquiries submitted by website visitors</p>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead>
                <tr class="border-b border-gray-200 text-dark font-extrabold uppercase tracking-wider">
                    <th class="pb-3 px-3">Customer</th>
                    <th class="pb-3 px-3">Service / Request</th>
                    <th class="pb-3 px-3">Message</th>
                    <th class="pb-3 px-3">Submitted</th>
                    <th class="pb-3 px-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($inquiries as $inquiry)
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="py-4 px-3 font-bold text-dark">
                            <div>{{ $inquiry->name }}</div>
                            <div class="text-[11px] text-dark/50 font-normal">{{ $inquiry->email }}</div>
                            <div class="text-[11px] text-dark/40 font-normal">{{ $inquiry->phone }}</div>
                        </td>
                        <td class="py-4 px-3">
                            <span class="px-3 py-1 rounded-full bg-primary/10 text-primary font-bold text-[11px]">
                                {{ $inquiry->service ?? 'General Inquiry' }}
                            </span>
                        </td>
                        <td class="py-4 px-3 max-w-xs text-dark/70 leading-relaxed">
                            <p class="line-clamp-2">{{ $inquiry->message }}</p>
                        </td>
                        <td class="py-4 px-3 text-dark/50 whitespace-nowrap">
                            {{ $inquiry->created_at ? $inquiry->created_at->format('M j, Y g:i A') : 'N/A' }}
                        </td>
                        <td class="py-4 px-3 text-right">
                            <form method="POST" action="{{ route('admin.inquiries.destroy', $inquiry) }}" onsubmit="return confirm('Delete this inquiry?');" class="inline-block">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="p-2 text-rose-500 hover:bg-rose-50 rounded-lg transition-colors" title="Delete Inquiry">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-12 text-center text-dark/40 font-medium">
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
