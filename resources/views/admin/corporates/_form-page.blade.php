@php /** @var \App\Models\CorporateAccount|null $corporate */ $corporate = $corporate ?? null; @endphp

@include('admin.partials._form-errors', ['heading' => 'The corporate account was not saved. Please check the following:'])

<div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm">
    <form method="POST" action="{{ $corporate ? route('admin.corporates.update', $corporate) : route('admin.corporates.store') }}" class="space-y-6">
        @csrf
        @if($corporate) @method('PUT') @endif

        <div class="p-4 rounded-2xl bg-gray-50 border border-gray-200 space-y-4">
            <span class="text-xs font-bold uppercase tracking-wider text-primary block">Corporate Details</span>
            @include('admin.corporates._fields', ['account' => $corporate])
        </div>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
            <a href="{{ $corporate ? route('admin.corporates.show', $corporate) : route('admin.corporates.index') }}" class="px-5 py-2.5 bg-gray-100 text-dark/70 font-bold text-xs rounded-xl hover:bg-gray-200 transition-all">Cancel</a>
            <button type="submit" class="px-6 py-2.5 bg-primary text-white font-bold text-xs rounded-xl hover:bg-primary-dark transition-all shadow-md flex items-center gap-2">
                <i data-lucide="check-circle" class="w-4 h-4"></i>
                <span>{{ $corporate ? 'Save Changes' : 'Create Corporate Account' }}</span>
            </button>
        </div>
    </form>
</div>
