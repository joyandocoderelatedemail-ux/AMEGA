@php
    /** @var \App\Models\CorporateAccount|null $corporate */
    $corporate = $corporate ?? null;
    // A company or a group: the account being edited decides, otherwise ?kind on the New page.
    $isGroup = $corporate ? $corporate->isGroup() : ($kind ?? 'corporate') === 'group';
@endphp

@include('admin.partials._form-errors', ['heading' => $isGroup ? 'The group was not saved. Please check the following:' : 'The corporate account was not saved. Please check the following:'])

@include('admin.partials._uppercase-inputs')

<div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-200 shadow-card">
    <form data-uppercase method="POST" action="{{ $corporate ? route('admin.corporates.update', $corporate) : route('admin.corporates.store') }}" class="space-y-6">
        @csrf
        @if($corporate) @method('PUT') @endif
        @if($isGroup && ! $corporate)<input type="hidden" name="kind" value="group">@endif

        <div class="p-4 rounded-2xl bg-gray-50 border border-gray-200 space-y-4">
            <span class="text-xs font-bold uppercase tracking-wider text-primary block">{{ $isGroup ? 'Group Details' : 'Corporate Details' }}</span>
            @if ($isGroup)
                @include('admin.corporates._group-fields', ['account' => $corporate])
            @else
                @include('admin.corporates._fields', ['account' => $corporate])
            @endif
        </div>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
            <a href="{{ $corporate ? route('admin.corporates.show', $corporate) : route('admin.corporates.index', $isGroup ? ['kind' => 'group'] : []) }}" class="px-5 py-2.5 bg-gray-100 text-dark/70 font-bold text-xs rounded-xl hover:bg-gray-200 transition-all">Cancel</a>
            <button type="submit" class="px-6 py-2.5 bg-primary text-white font-bold text-xs rounded-xl hover:bg-primary-dark transition-all shadow-md flex items-center gap-2">
                <i data-lucide="check-circle" class="w-4 h-4"></i>
                <span>{{ $corporate ? 'Save Changes' : ($isGroup ? 'Create Group' : 'Create Corporate Account') }}</span>
            </button>
        </div>
    </form>
</div>
