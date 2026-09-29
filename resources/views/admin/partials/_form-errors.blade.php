{{-- Lists why a form was not saved. The admin layout only shows success and error flashes, so
     validation problems (a used email, a short password) would otherwise vanish without a word. --}}
@if ($errors->any())
    <div role="alert" class="p-4 rounded-2xl bg-rose-50 text-rose-800 text-sm border border-rose-200 flex items-start gap-3">
        <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-600 shrink-0 mt-0.5"></i>
        <div>
            <p class="font-bold">{{ $heading ?? 'The account was not saved. Please check the following:' }}</p>
            <ul class="mt-1 list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
