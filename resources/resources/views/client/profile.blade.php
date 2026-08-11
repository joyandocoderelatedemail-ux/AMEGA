@extends('layouts.app')

@section('title', 'My Profile (Viewing Only) - AMEGA Travel & Tours')

@section('content')
<!-- Hero Header -->
<section class="relative pt-32 pb-16 bg-navy text-white overflow-hidden">
    <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-primary/30 via-navy to-navy"></div>
    <div class="section-dots opacity-10"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
            <div class="flex items-center gap-4">
                @if($user->profile_photo)
                    <img src="{{ asset('storage/' . $user->profile_photo) }}" alt="{{ $user->name }}" class="w-16 h-16 rounded-full object-cover border-2 border-white/20 shadow-lg shrink-0">
                @else
                    <div class="w-16 h-16 rounded-full bg-accent text-dark font-heading font-extrabold text-2xl flex items-center justify-center shadow-lg border-2 border-white/20 shrink-0">
                        {{ substr($user->full_name, 0, 1) }}
                    </div>
                @endif
                <div>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-accent/20 backdrop-blur-md text-accent font-extrabold text-[11px] uppercase tracking-wider mb-1 border border-accent/30">
                        <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                        Profile Details (Viewing Only)
                    </span>
                    <h1 class="font-heading text-2xl sm:text-3xl font-bold text-white">{{ $user->full_name }}</h1>
                    <p class="text-xs text-white/60 mt-0.5">{{ $user->email }} • {{ $user->account_category ?? 'Individual' }} Traveler</p>
                </div>
            </div>

            <a href="{{ route('client.dashboard') }}" class="px-6 py-3 bg-white/10 text-white font-heading font-extrabold text-xs rounded-full hover:bg-white/20 transition-all border border-white/20 flex items-center gap-2">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                <span>Back to Dashboard</span>
            </a>
        </div>
    </div>
</section>

<!-- Main Profile Body with Client Sidebar -->
<section class="py-12 bg-gray-50 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            
            <!-- Left Column: Client Sidebar Navigation -->
            <div class="lg:col-span-1">
                @include('client.partials.sidebar')
            </div>

            <!-- Right Column: View-Only Profile Cards (3 Columns) -->
            <div class="lg:col-span-3 space-y-8">
                
                <!-- Notice Banner: View Only Mode -->
                <div class="p-4 rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 text-xs flex items-center gap-3">
                    <i data-lucide="shield-check" class="w-5 h-5 text-amber-600 shrink-0"></i>
                    <div>
                        <span class="font-bold">Read-Only Profile Mode:</span>
                        <span>Your account registration information, passport details, government IDs, and signature are displayed below for verification only.</span>
                    </div>
                </div>

                <!-- 1. Personal Information & Residential Address Card -->
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm space-y-6">
                    <div class="flex items-center gap-3 border-b border-gray-100 pb-4">
                        <div class="w-10 h-10 rounded-2xl bg-primary/10 text-primary flex items-center justify-center font-bold">1</div>
                        <div>
                            <h2 class="font-heading text-lg font-bold text-dark">Personal Information & Residential Address</h2>
                            <p class="text-xs text-dark/50">Full legal name, contact credentials, and citizenship</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs">
                        
                        <!-- Name Breakdown -->
                        <div class="sm:col-span-2 p-4 rounded-2xl bg-gray-50 border border-gray-100 space-y-2">
                            <span class="text-primary font-bold uppercase tracking-wider text-[10px] block">Full Legal Name Breakdown</span>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-[11px]">
                                <div>
                                    <span class="text-dark/40 block">Given Name:</span>
                                    <span class="font-bold text-dark text-sm">{{ $user->first_name ?? 'N/A' }}</span>
                                </div>
                                <div>
                                    <span class="text-dark/40 block">Middle Name:</span>
                                    <span class="font-bold text-dark text-sm">{{ $user->middle_name ?? 'N/A' }}</span>
                                </div>
                                <div>
                                    <span class="text-dark/40 block">Last Name / Surname:</span>
                                    <span class="font-bold text-dark text-sm">{{ $user->last_name ?? 'N/A' }}</span>
                                </div>
                                <div>
                                    <span class="text-dark/40 block">Suffix:</span>
                                    <span class="font-bold text-dark text-sm">{{ $user->suffix ?? 'None' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Email & Phone -->
                        <div>
                            <span class="text-dark/40 font-bold uppercase tracking-wider block text-[10px] mb-1">Email Address</span>
                            <div class="p-3 bg-gray-50 rounded-xl font-semibold text-dark">{{ $user->email }}</div>
                        </div>

                        <div>
                            <span class="text-dark/40 font-bold uppercase tracking-wider block text-[10px] mb-1">Phone / Mobile Number</span>
                            <div class="p-3 bg-gray-50 rounded-xl font-semibold text-dark">{{ $user->phone ?? 'Not Provided' }}</div>
                        </div>

                        <!-- Complete Address -->
                        <div class="sm:col-span-2 p-4 rounded-2xl bg-gray-50 border border-gray-100 space-y-2">
                            <span class="text-primary font-bold uppercase tracking-wider text-[10px] block">Residential Address Information</span>
                            <div class="space-y-1.5 text-xs">
                                <div>
                                    <span class="text-dark/40 block text-[10px]">Street / House No / Barangay:</span>
                                    <span class="font-bold text-dark">{{ $user->address_line ?? $user->address ?? 'Not Provided' }}</span>
                                </div>
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-[11px] pt-1">
                                    <div>
                                        <span class="text-dark/40 block">City / Municipality:</span>
                                        <span class="font-semibold text-dark">{{ $user->city ?? 'N/A' }}</span>
                                    </div>
                                    <div>
                                        <span class="text-dark/40 block">Province / State:</span>
                                        <span class="font-semibold text-dark">{{ $user->province ?? 'N/A' }}</span>
                                    </div>
                                    <div>
                                        <span class="text-dark/40 block">Postal Code:</span>
                                        <span class="font-semibold text-dark">{{ $user->postal_code ?? 'N/A' }}</span>
                                    </div>
                                    <div>
                                        <span class="text-dark/40 block">Country:</span>
                                        <span class="font-semibold text-dark">{{ $user->country ?? 'Philippines' }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <span class="text-dark/40 font-bold uppercase tracking-wider block text-[10px] mb-1">Nationality & Citizenship</span>
                            <div class="p-3 bg-gray-50 rounded-xl font-semibold text-dark">{{ $user->nationality ?? 'Filipino' }}</div>
                        </div>

                        <!-- Emergency Contact -->
                        <div class="sm:col-span-2 p-4 rounded-2xl bg-gray-50 border border-gray-100 space-y-2">
                            <span class="text-primary font-bold uppercase tracking-wider text-[10px] block">Emergency Contact Person</span>
                            <div class="grid grid-cols-3 gap-3 text-[11px]">
                                <div>
                                    <span class="text-dark/40 block">Contact Name:</span>
                                    <span class="font-bold text-dark">{{ $user->emergency_contact_name ?? 'N/A' }}</span>
                                </div>
                                <div>
                                    <span class="text-dark/40 block">Contact Phone:</span>
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

                <!-- 2. Passport Records & Government ID Photo Card -->
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm space-y-6">
                    <div class="flex items-center gap-3 border-b border-gray-100 pb-4">
                        <div class="w-10 h-10 rounded-2xl bg-accent text-dark flex items-center justify-center font-bold">2</div>
                        <div>
                            <h2 class="font-heading text-lg font-bold text-dark">Passport Records & Government ID Verification</h2>
                            <p class="text-xs text-dark/50">Travel documents, account category, and uploaded ID document</p>
                        </div>
                    </div>

                    <div class="space-y-5 text-xs">
                        <div>
                            <span class="text-dark/40 font-bold uppercase tracking-wider block text-[10px] mb-1">Traveler Account Category</span>
                            <span class="inline-block px-3 py-1 rounded-full bg-primary/10 text-primary font-bold text-xs">
                                {{ $user->account_category ?? 'Individual' }} Category
                            </span>
                        </div>

                        <!-- Passport Records -->
                        <div class="p-4 rounded-2xl bg-gray-50 border border-gray-100 space-y-2">
                            <span class="text-primary font-bold uppercase tracking-wider text-[10px] block">Passport Information</span>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-[11px]">
                                <div>
                                    <span class="text-dark/40 block">Passport Number:</span>
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

                        <!-- Government ID & Document Photo Upload -->
                        <div class="p-4 rounded-2xl bg-gray-50 border border-gray-100 space-y-3">
                            <span class="text-primary font-bold uppercase tracking-wider text-[10px] block">Government ID Records</span>
                            <div class="grid grid-cols-2 gap-3 text-[11px]">
                                <div>
                                    <span class="text-dark/40 block">ID Type:</span>
                                    <span class="font-bold text-dark">{{ $user->government_id_type ?? 'Not Provided' }}</span>
                                </div>
                                <div>
                                    <span class="text-dark/40 block">ID Number:</span>
                                    <span class="font-mono font-bold text-dark">{{ $user->government_id_number ?? 'N/A' }}</span>
                                </div>
                            </div>

                            <div class="pt-2 border-t border-gray-200">
                                <span class="text-dark/40 font-bold uppercase tracking-wider block text-[10px] mb-2">Uploaded Government ID Document</span>
                                @if($user->government_id_photo)
                                    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                                        <div class="w-48 h-32 bg-white rounded-xl border border-gray-200 p-2 flex items-center justify-center overflow-hidden shadow-inner">
                                            @if(str_contains(strtolower($user->government_id_photo), '.pdf'))
                                                <div class="text-rose-600 font-bold text-xs text-center flex items-center gap-2">
                                                    <i data-lucide="file-text" class="w-6 h-6"></i>
                                                    <span>PDF Document</span>
                                                </div>
                                            @else
                                                <img src="{{ asset('storage/' . $user->government_id_photo) }}" alt="Government ID" class="max-h-full max-w-full object-contain">
                                            @endif
                                        </div>
                                        <a href="{{ asset('storage/' . $user->government_id_photo) }}" target="_blank" class="px-4 py-2 bg-emerald-600 text-white font-bold text-xs rounded-xl hover:bg-emerald-700 transition-colors shadow-sm flex items-center gap-2">
                                            <i data-lucide="external-link" class="w-4 h-4"></i>
                                            <span>Open Full Document</span>
                                        </a>
                                    </div>
                                @else
                                    <span class="text-[11px] text-dark/40 italic">No government ID document uploaded</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. Profile Photo & E-Signature Card -->
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm space-y-6">
                    <div class="flex items-center gap-3 border-b border-gray-100 pb-4">
                        <div class="w-10 h-10 rounded-2xl bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold">3</div>
                        <div>
                            <h2 class="font-heading text-lg font-bold text-dark">Profile Photo Avatar & E-Signature Record</h2>
                            <p class="text-xs text-dark/50">Digital profile image and authorization signature</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <!-- Profile Photo -->
                        <div class="p-4 rounded-2xl bg-gray-50 border border-gray-100 flex items-center gap-4">
                            @if($user->profile_photo)
                                <img src="{{ asset('storage/' . $user->profile_photo) }}" alt="{{ $user->name }}" class="w-20 h-20 rounded-2xl object-cover border border-gray-200 shrink-0">
                            @else
                                <div class="w-20 h-20 rounded-2xl bg-navy text-accent font-bold text-2xl flex items-center justify-center shrink-0">
                                    {{ substr($user->full_name, 0, 1) }}
                                </div>
                            @endif
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40 block">Profile Avatar</span>
                                <div class="text-xs font-bold text-dark mt-0.5">{{ $user->profile_photo ? 'Custom Image Uploaded' : 'Default Avatar' }}</div>
                            </div>
                        </div>

                        <!-- E-Signature -->
                        <div class="p-4 rounded-2xl bg-gray-50 border border-gray-100 flex flex-col justify-center">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40 block mb-2">Digital E-Signature Record</span>
                            @if($user->signature)
                                <div class="bg-white rounded-xl p-3 border border-gray-200 max-w-sm flex items-center justify-center shadow-inner">
                                    <img src="{{ $user->signature }}" alt="E-Signature" class="max-h-20 object-contain">
                                </div>
                            @else
                                <div class="text-xs text-dark/40 italic p-3 bg-white rounded-xl border border-gray-200 text-center">
                                    No e-signature recorded
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>
@endsection
