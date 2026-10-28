@php
    // Old input belongs to the form that was sent: the add form (no faq_id) or this question's.
    $mine = $faq ? (int) old('faq_id') === $faq->id : old('faq_id') === null && old('question') !== null;
@endphp
<label class="block">
    <span class="block text-xs font-semibold text-slate-700 mb-1">Question</span>
    <input type="text" name="question" maxlength="255" required value="{{ $mine ? old('question') : $faq?->question }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    @if ($mine)
        @error('question') <span class="block text-xs text-rose-600 mt-1">{{ $message }}</span> @enderror
    @endif
</label>
<label class="block">
    <span class="block text-xs font-semibold text-slate-700 mb-1">Answer</span>
    <textarea name="answer" rows="4" maxlength="3000" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ $mine ? old('answer') : $faq?->answer }}</textarea>
    @if ($mine)
        @error('answer') <span class="block text-xs text-rose-600 mt-1">{{ $message }}</span> @enderror
    @endif
</label>
<label class="inline-flex items-center gap-2 text-sm text-slate-700">
    <input type="checkbox" name="is_published" value="1" @checked($mine ? old('is_published') : ($faq?->is_published ?? true)) class="rounded border-slate-300">
    Show on the website
</label>
