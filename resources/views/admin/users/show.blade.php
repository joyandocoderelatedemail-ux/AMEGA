@extends('layouts.admin')

@section('title', 'Client Profile Details - AMEGA Admin')
@section('page_title', 'Account Profile Inspection')

@section('content')
<div class="max-w-4xl mx-auto space-y-8">
    
    <!-- Profile Banner Card -->
    <div class="bg-navy rounded-3xl p-6 sm:p-8 text-white relative overflow-hidden shadow-xl border border-white/10 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
        <div class="flex items-center gap-5">
            @if($user->profile_photo_url)
                <img src="{{ $user->profile_photo_url }}" alt="{{ $user->name }}" class="w-20 h-20 rounded-full object-cover border-4 border-white/20 shadow-lg shrink-0">
            @else
                <div class="w-20 h-20 rounded-full bg-accent text-dark font-heading font-extrabold text-3xl flex items-center justify-center border-4 border-white/20 shadow-lg shrink-0">
                    {{ substr($user->full_name, 0, 1) }}
                </div>
            @endif

            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="px-3 py-1 rounded-full bg-accent text-dark font-extrabold text-[10px] uppercase tracking-wider">
                        {{ $user->account_category ?? 'Individual' }} Category
                    </span>
                    <span class="px-3 py-1 rounded-full bg-white/10 text-white font-bold text-[10px] uppercase tracking-wider border border-white/20">
                        {{ ucfirst($user->role) }}
                    </span>
                </div>
                <h2 class="font-heading text-2xl font-bold text-white">{{ $user->full_name }}</h2>
                <p class="text-xs text-white/70 mt-0.5">{{ $user->email }} • Registered {{ $user->created_at ? $user->created_at->format('M j, Y') : 'Recently' }}</p>
            </div>
        </div>

        <a href="{{ route('admin.users.index') }}" class="px-5 py-2.5 bg-white/10 text-white font-bold text-xs rounded-full hover:bg-white/20 transition-all border border-white/20">
            ← Back to Accounts Directory
        </a>
    </div>

    <!-- 3-Part Registration Information Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        
        <!-- Part 1: Personal, Name Breakdown, Address & Emergency Contact -->
        <div class="bg-white rounded-3xl p-6 sm:p-7 border border-gray-100 shadow-sm space-y-5">
            <div class="flex items-center gap-3 border-b border-gray-100 pb-3">
                <div class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold">1</div>
                <div>
                    <h3 class="font-heading text-base font-bold text-dark">Personal, Address & Contact Details</h3>
                    <p class="text-[11px] text-dark/50">Split name records, residential address & emergency contact</p>
                </div>
            </div>

            <div class="space-y-4 text-xs">
                <!-- Name Breakdown Card -->
                <div class="p-3.5 rounded-2xl bg-gray-50 border border-gray-100 space-y-2">
                    <span class="text-primary font-bold uppercase tracking-wider text-[10px] block">Full Name Breakdown</span>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-[11px]">
                        <div>
                            <span class="text-dark/40 block">Given Name:</span>
                            <span class="font-bold text-dark">{{ $user->first_name ?? 'N/A' }}</span>
                        </div>
                        <div>
                            <span class="text-dark/40 block">Middle Name:</span>
                            <span class="font-bold text-dark">{{ $user->middle_name ?? 'N/A' }}</span>
                        </div>
                        <div>
                            <span class="text-dark/40 block">Surname:</span>
                            <span class="font-bold text-dark">{{ $user->last_name ?? 'N/A' }}</span>
                        </div>
                        <div>
                            <span class="text-dark/40 block">Suffix:</span>
                            <span class="font-bold text-dark">{{ $user->suffix ?? 'None' }}</span>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <span class="text-dark/40 font-bold uppercase tracking-wider block text-[10px]">Email Address</span>
                        <span class="font-semibold text-dark">{{ $user->email }}</span>
                    </div>
                    <div>
                        <span class="text-dark/40 font-bold uppercase tracking-wider block text-[10px]">Phone Number</span>
                        <span class="font-semibold text-dark">{{ $user->phone ?? 'Not provided' }}</span>
                    </div>
                </div>

                <!-- Complete Address Details Card -->
                <div class="p-3.5 rounded-2xl bg-gray-50 border border-gray-100 space-y-2">
                    <span class="text-primary font-bold uppercase tracking-wider text-[10px] block">Residential Address Information</span>
                    <div class="space-y-1.5 text-[11px]">
                        <div>
                            <span class="text-dark/40 block">Street / House No:</span>
                            <span class="font-bold text-dark">{{ $user->address_line ?? $user->address ?? 'Not provided' }}</span>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <span class="text-dark/40 block">City / Municipality:</span>
                                <span class="font-semibold text-dark">{{ $user->city ?? 'N/A' }}</span>
                            </div>
                            <div>
                                <span class="text-dark/40 block">Province / Region:</span>
                                <span class="font-semibold text-dark">{{ $user->province ?? 'N/A' }}</span>
                            </div>
                            <div>
                                <span class="text-dark/40 block">Postal Code:</span>
                                <span class="font-semibold text-dark">{{ $user->postal_code ?? 'N/A' }}</span>
                            </div>
                        </div>
                        <div>
                            <span class="text-dark/40 block">Country:</span>
                            <span class="font-semibold text-dark">{{ $user->country ?? 'Philippines' }}</span>
                        </div>
                    </div>
                </div>

                <div>
                    <span class="text-dark/40 font-bold uppercase tracking-wider block text-[10px]">Nationality & Citizenship</span>
                    <span class="font-semibold text-dark">{{ $user->nationality ?? 'Filipino' }}</span>
                </div>

                <!-- Emergency Contact Person -->
                <div class="p-3.5 rounded-2xl bg-gray-50 border border-gray-100 space-y-2">
                    <span class="text-primary font-bold uppercase tracking-wider text-[10px] block">Emergency Contact Person</span>
                    <div class="grid grid-cols-3 gap-2 text-[11px]">
                        <div>
                            <span class="text-dark/40 block">Name:</span>
                            <span class="font-bold text-dark">{{ $user->emergency_contact_name ?? 'N/A' }}</span>
                        </div>
                        <div>
                            <span class="text-dark/40 block">Phone:</span>
                            <span class="font-bold text-dark">{{ $user->emergency_contact_phone ?? 'N/A' }}</span>
                        </div>
                        <div>
                            <span class="text-dark/40 block">Relationship:</span>
                            <span class="font-bold text-dark">{{ $user->emergency_contact_relationship ?? 'N/A' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Part 2: Passport Information & Government ID Records & ID Photo -->
        <div class="bg-white rounded-3xl p-6 sm:p-7 border border-gray-100 shadow-sm space-y-5">
            <div class="flex items-center gap-3 border-b border-gray-100 pb-3">
                <div class="w-8 h-8 rounded-xl bg-accent text-dark flex items-center justify-center font-bold">2</div>
                <div>
                    <h3 class="font-heading text-base font-bold text-dark">Passport & Government ID Records</h3>
                    <p class="text-[11px] text-dark/50">Passport, government identification and uploaded ID photo</p>
                </div>
            </div>

            <div class="space-y-3.5 text-xs">
                <div>
                    <span class="text-dark/40 font-bold uppercase tracking-wider block text-[10px]">Account Category</span>
                    <span class="inline-block px-3 py-1 rounded-full bg-primary/10 text-primary font-bold text-xs mt-0.5">
                        {{ $user->account_category ?? 'Individual' }}
                    </span>
                </div>

                <!-- Passport Records -->
                <div class="p-3.5 rounded-2xl bg-gray-50 border border-gray-100 space-y-2">
                    <span class="text-primary font-bold uppercase tracking-wider text-[10px] block">Passport Details</span>
                    <div class="grid grid-cols-3 gap-2 text-[11px]">
                        <div>
                            <span class="text-dark/40 block">Passport #:</span>
                            <span class="font-mono font-bold text-dark">{{ $user->passport_number ?? 'Not Provided' }}</span>
                        </div>
                        <div>
                            <span class="text-dark/40 block">Expiry Date:</span>
                            <span class="font-semibold text-dark">{{ $user->passport_expiry ? $user->passport_expiry->format('M j, Y') : 'N/A' }}</span>
                        </div>
                        <div>
                            <span class="text-dark/40 block">Issuing Country:</span>
                            <span class="font-semibold text-dark">{{ $user->passport_country ?? 'Philippines' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Government ID Records & Photo -->
                <div class="p-3.5 rounded-2xl bg-gray-50 border border-gray-100 space-y-3">
                    <span class="text-primary font-bold uppercase tracking-wider text-[10px] block">Government-Issued Identification</span>
                    <div class="grid grid-cols-2 gap-2 text-[11px]">
                        <div>
                            <span class="text-dark/40 block">ID Type:</span>
                            <span class="font-bold text-dark">{{ $user->government_id_type ?? 'Not Provided' }}</span>
                        </div>
                        <div>
                            <span class="text-dark/40 block">ID Number:</span>
                            <span class="font-mono font-bold text-dark">{{ $user->government_id_number ?? 'N/A' }}</span>
                        </div>
                    </div>

                    <!-- Government ID Uploaded Photo Preview -->
                    <div class="pt-2 border-t border-gray-200">
                        <span class="text-dark/40 font-bold uppercase tracking-wider block text-[10px] mb-1.5">Uploaded ID Photo / Document</span>
                        @if($user->government_id_photo_url)
                            <div class="space-y-2">
                                <div class="rounded-xl border border-gray-200 overflow-hidden bg-white p-2 text-center max-w-xs">
                                    @if(str_contains(strtolower($user->government_id_photo), '.pdf'))
                                        <div class="p-4 bg-rose-50 text-rose-700 font-bold text-xs rounded-lg flex items-center justify-center gap-2">
                                            <i data-lucide="file-text" class="w-5 h-5"></i>
                                            <span>PDF Document Uploaded</span>
                                        </div>
                                    @else
                                        <img src="{{ $user->government_id_photo_url }}" alt="Government ID Photo" class="max-h-48 rounded-lg object-contain mx-auto">
                                    @endif
                                </div>
                                <a href="{{ $user->government_id_photo_url }}" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 text-white font-bold text-[11px] rounded-lg hover:bg-emerald-700 transition-colors shadow-sm">
                                    <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
                                    <span>Open Original ID Document</span>
                                </a>
                            </div>
                        @else
                            <span class="text-[11px] text-dark/40 italic">No government ID photo uploaded</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Part 3: Profile Upload & E-Signature Card (Full Width) -->
        <div class="md:col-span-2 bg-white rounded-3xl p-6 sm:p-7 border border-gray-100 shadow-sm space-y-5">
            <div class="flex items-center gap-3 border-b border-gray-100 pb-3">
                <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold">3</div>
                <div>
                    <h3 class="font-heading text-base font-bold text-dark">Profile Photo & E-Signature Record</h3>
                    <p class="text-[11px] text-dark/50">Digital avatar and stored e-signature authorization</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Profile Avatar Display -->
                <div class="p-4 rounded-2xl bg-gray-50 border border-gray-100 flex items-center gap-4">
                    @if($user->profile_photo_url)
                        <img src="{{ $user->profile_photo_url }}" alt="{{ $user->name }}" class="w-20 h-20 rounded-2xl object-cover border border-gray-200 shrink-0">
                    @else
                        <div class="w-20 h-20 rounded-2xl bg-navy text-accent font-bold text-2xl flex items-center justify-center shrink-0">
                            {{ substr($user->full_name, 0, 1) }}
                        </div>
                    @endif
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40 block">Uploaded Avatar</span>
                        <div class="text-xs font-bold text-dark mt-0.5">{{ $user->profile_photo_url ? 'Custom Image Uploaded' : 'Default Avatar' }}</div>
                    </div>
                </div>

                <!-- Digital Signature Display -->
                <div class="p-4 rounded-2xl bg-gray-50 border border-gray-100 flex flex-col justify-center">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40 block mb-2">Recorded Digital E-Signature</span>
                    @if($user->signature)
                        <div class="bg-white rounded-xl p-3 border border-gray-200 max-w-sm flex items-center justify-center shadow-inner">
                            <img src="{{ $user->signature }}" alt="E-Signature" class="max-h-20 object-contain">
                        </div>
                    @else
                        <div class="text-xs text-dark/40 italic p-3 bg-white rounded-xl border border-gray-200 text-center">
                            No e-signature provided during registration
                        </div>
                    @endif
                </div>
            </div>
        </div>

    </div>
</div>
@endsection
