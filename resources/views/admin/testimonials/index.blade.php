@extends('layouts.admin')

@section('title', 'Testimonials Management - AMEGA Admin')
@section('page_title', 'Client Reviews')

@section('content')
<div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="font-heading text-xl font-bold text-dark">Client Testimonials</h2>
            <p class="text-xs text-dark/50">Manage published reviews on the public website</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        @foreach ($testimonials as $t)
            <div class="p-6 rounded-2xl bg-gray-50 border border-gray-100 space-y-3 relative">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="font-heading font-bold text-sm text-dark">{{ $t->name }}</span>
                        <span class="text-xs text-dark/40 block">{{ $t->location }}</span>
                    </div>
                    <form method="POST" action="{{ route('admin.testimonials.destroy', $t) }}" onsubmit="return confirm('Delete this review?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-rose-500 hover:text-rose-700 p-1">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                    </form>
                </div>
                <p class="text-xs text-dark/70 italic leading-relaxed">"{{ $t->comment }}"</p>
            </div>
        @endforeach
    </div>
</div>
@endsection
