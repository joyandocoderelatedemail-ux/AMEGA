{{--
    "Save as Pending" on a desk form: keeps what has been typed without opening a file or sending it
    for approval (DeskDraftController). Placed inside the form, beside its Save buttons.
    $desk: immigration | visa | srrv. $title: what the form is, e.g. "SRRV application".
    $class: the button's look, to match the form's other buttons.
--}}
@php
    $pendingStore = ['immigration' => 'admin.immigration.pending.store', 'visa' => 'visa.pending.store', 'srrv' => 'srrv.pending.store'][$desk];
@endphp
<input type="hidden" name="_pending_title" value="{{ $title }}">
<input type="hidden" name="_pending_url" value="{{ request()->fullUrlWithoutQuery(['draft']) }}">
@if (request()->filled('draft'))
    <input type="hidden" name="_desk_draft" value="{{ request()->integer('draft') }}">
@endif
<button type="submit" formaction="{{ route($pendingStore) }}" formnovalidate data-save-pending
        title="Keep this form to finish later. No file is opened and nothing is sent for approval."
        class="{{ $class ?? 'px-5 py-2.5 bg-white border border-amber-300 text-amber-800 font-bold text-xs rounded-xl hover:bg-amber-50 transition-all inline-flex items-center justify-center gap-2' }}">
    <i data-lucide="clock" class="w-4 h-4"></i>
    <span>Save as Pending</span>
</button>
