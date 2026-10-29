@extends('layouts.admin')

@section('title', 'FAQs - AMEGA Admin')
@section('page_title', 'FAQs')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div>
        <h2 class="text-xl font-bold text-slate-900">Frequently Asked Questions</h2>
        <p class="text-sm text-slate-500 mt-0.5">The questions and answers on the public website's FAQ section and page, in this order. Hidden questions stay here but are not shown.</p>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm divide-y divide-slate-100">
        @forelse ($faqs as $faq)
            <details class="group" @if ($errors->any() && (int) old('faq_id') === $faq->id) open @endif>
                <summary class="flex items-center justify-between gap-3 px-5 py-4 cursor-pointer list-none hover:bg-slate-50">
                    <span class="flex items-start gap-3 min-w-0">
                        <span class="w-8 h-8 rounded-lg bg-slate-100 text-slate-600 text-xs font-semibold flex items-center justify-center shrink-0">{{ $faq->sort_order }}</span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-slate-900">{{ $faq->question }}</span>
                            <span class="block text-xs text-slate-500 truncate">{{ $faq->answer }}</span>
                        </span>
                    </span>
                    <span class="flex items-center gap-3 shrink-0">
                        @unless ($faq->is_published)
                            <span class="px-2 py-0.5 rounded-md bg-amber-50 text-amber-700 text-[11px] font-semibold">Hidden</span>
                        @endunless
                        <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400 group-open:rotate-180 transition-transform"></i>
                    </span>
                </summary>

                <div class="px-5 pb-5 pt-1 space-y-4">
                    <form method="POST" action="{{ route('admin.faqs.update', $faq) }}" class="space-y-4">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="faq_id" value="{{ $faq->id }}">
                        @include('admin.faqs._fields', ['faq' => $faq])
                        <div class="flex flex-wrap items-end justify-between gap-3">
                            <label class="block">
                                <span class="block text-xs font-semibold text-slate-700 mb-1">Order</span>
                                <input type="number" name="sort_order" min="0" max="9999" value="{{ old('faq_id') == $faq->id ? old('sort_order') : $faq->sort_order }}" class="w-24 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            </label>
                            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-navy-700 text-white text-sm font-semibold hover:bg-navy-800">
                                <i data-lucide="save" class="w-4 h-4"></i> Save
                            </button>
                        </div>
                    </form>
                    @if (auth()->user()?->isAdmin())
                    <form method="POST" action="{{ route('admin.faqs.destroy', $faq) }}" onsubmit="return confirm('Remove this question from the website?');" class="text-right">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-700">Remove question</button>
                    </form>
                    @endif
                </div>
            </details>
        @empty
            <p class="px-5 py-10 text-center text-sm text-slate-500">No questions yet. The FAQ section stays hidden on the website until you add one.</p>
        @endforelse
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-slate-900 mb-4">Add a question</h3>
        <form method="POST" action="{{ route('admin.faqs.store') }}" class="space-y-4">
            @csrf
            @include('admin.faqs._fields', ['faq' => null])
            <div class="text-right">
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-navy-700 text-white text-sm font-semibold hover:bg-navy-800">
                    <i data-lucide="plus" class="w-4 h-4"></i> Add question
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
