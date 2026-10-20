@php
    /**
     * The client's profile picture on the add and edit forms: a round preview that shows
     * the chosen photo straight away, one button to add or change it, and a way to back
     * out of a photo picked by mistake. Stored on the private document disk like the IDs.
     *
     * @var \App\Models\User|null $user
     */
    $user = $user ?? null;
    $currentPhoto = $user?->profile_photo_url;
@endphp

<div class="p-4 rounded-2xl bg-gray-50 border border-gray-200 flex flex-col sm:flex-row sm:items-center gap-5"
     x-data="{
        saved: @js($currentPhoto),
        preview: @js($currentPhoto),
        fileName: '',
        choose(event) {
            const file = event.target.files[0];
            if (! file) { return; }
            this.fileName = file.name;
            this.preview = URL.createObjectURL(file);
        },
        undo() {
            this.$refs.photo.value = '';
            this.fileName = '';
            this.preview = this.saved;
        },
     }">
    <div class="w-24 h-24 rounded-full overflow-hidden bg-white border border-gray-200 shadow-card flex items-center justify-center shrink-0"
         :class="preview ? '' : 'border-dashed border-2 border-gray-300'">
        <img x-show="preview" :src="preview" alt="Profile picture preview" class="w-full h-full object-cover" @if(! $currentPhoto) style="display: none" @endif>
        <span x-show="! preview" @if($currentPhoto) style="display: none" @endif><i data-lucide="user-round" class="w-9 h-9 text-dark/25"></i></span>
    </div>

    <div class="min-w-0">
        <span class="text-xs font-bold uppercase tracking-wider text-primary block">Profile Picture</span>
        <p class="text-[11px] text-dark/50 mt-0.5">A clear photo of the client's face. JPG, PNG or WEBP, up to 5 MB. Optional.</p>

        <div class="flex flex-wrap items-center gap-2 mt-3">
            <label for="profile_photo" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-primary/10 text-primary font-bold text-xs cursor-pointer hover:bg-primary/20 transition-colors focus-within:ring-2 focus-within:ring-primary">
                <i data-lucide="camera" class="w-4 h-4"></i>
                <span x-text="preview ? 'Change picture' : 'Add picture'">{{ $currentPhoto ? 'Change picture' : 'Add picture' }}</span>
                <input id="profile_photo" x-ref="photo" type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="choose($event)">
            </label>
            <button type="button" x-show="fileName" @click="undo()" style="display: none"
                    class="px-3 py-2 rounded-xl text-xs font-bold text-dark/60 hover:bg-gray-200 transition-colors">
                Undo
            </button>
        </div>

        <p x-show="fileName" style="display: none" class="text-[11px] font-semibold text-emerald-700 mt-2">
            New picture: <span x-text="fileName"></span>. It is saved with the record.
        </p>
        @if($currentPhoto)
            <p x-show="! fileName" class="text-[10px] text-dark/40 mt-2">Choosing a new picture replaces the one on record.</p>
        @endif
        @error('profile_photo')
            <p class="text-[11px] font-semibold text-rose-600 mt-2">{{ $message }}</p>
        @enderror
    </div>
</div>
