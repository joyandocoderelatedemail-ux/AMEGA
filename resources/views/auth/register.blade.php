@extends('layouts.app')

@section('title', 'Create Account - Amega Travel and Tours Services')

@section('content')
<section class="min-h-screen pt-28 pb-16 flex items-center justify-center section-gradient-cool relative overflow-hidden">
    <div class="section-dots"></div>
    <div class="max-w-3xl w-full mx-auto px-4 sm:px-6 relative z-10">
        
        <!-- Logo & Header -->
        <div class="text-center mb-6">
            <a href="{{ route('home') }}" class="inline-block mb-3">
                <img src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED.png') }}" alt="Amega Travel and Tours Services" class="h-14 w-auto mx-auto object-contain">
            </a>
            <h1 class="font-heading text-3xl font-bold text-dark">Join Amega Travel and Tours Services</h1>
            <p class="text-dark/60 text-sm mt-1">Complete your 3-step traveler registration</p>
        </div>

        <!-- 3-Step Wizard Card -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 shadow-xl border border-gray-100 relative overflow-hidden">
            <div class="absolute top-0 left-0 right-0 h-2 bg-gradient-to-r from-accent via-primary to-navy"></div>

            <!-- Step Progress Indicator -->
            <div class="mb-8">
                <div class="flex items-center justify-between relative mb-2">
                    <div class="w-full absolute top-1/2 left-0 h-1 bg-gray-100 -z-0"></div>
                    <div id="progress-bar" class="absolute top-1/2 left-0 h-1 bg-primary transition-all duration-500 -z-0" style="width: 0%;"></div>

                    <!-- Step 1 Button -->
                    <button type="button" id="step-btn-1" class="w-10 h-10 rounded-full bg-primary text-white font-bold text-sm flex items-center justify-center relative z-10 shadow-md transition-all">1</button>

                    <!-- Step 2 Button -->
                    <button type="button" id="step-btn-2" class="w-10 h-10 rounded-full bg-gray-100 text-dark/50 font-bold text-sm flex items-center justify-center relative z-10 transition-all">2</button>

                    <!-- Step 3 Button -->
                    <button type="button" id="step-btn-3" class="w-10 h-10 rounded-full bg-gray-100 text-dark/50 font-bold text-sm flex items-center justify-center relative z-10 transition-all">3</button>
                </div>

                <div class="flex justify-between text-[11px] font-bold uppercase tracking-wider text-dark/60 pt-1">
                    <span id="step-lbl-1" class="text-primary">1. Personal, Address & Contact</span>
                    <span id="step-lbl-2">2. Passport & ID Photo</span>
                    <span id="step-lbl-3">3. Profile & Signature</span>
                </div>
            </div>

            <!-- Registration Form -->
            <form method="POST" action="{{ route('register') }}" enctype="multipart/form-data" id="register-form" class="space-y-6">
                @csrf
                <input type="hidden" name="signature" id="signature-input">

                <!-- STEP 1: Personal Information (Name Split, Address, Contact, Nationality & Emergency Contact) -->
                <div id="step-1" class="space-y-5">
                    <div class="border-b border-gray-100 pb-3">
                        <h2 class="font-heading text-lg font-bold text-dark">Step 1: Personal, Address & Contact Details</h2>
                        <p class="text-xs text-dark/50">Given name, surname, address, citizenship, and emergency contact</p>
                    </div>

                    <!-- Split Name Fields (First, Middle, Last & Suffix) -->
                    <div class="p-4 rounded-2xl bg-gray-50 border border-gray-200 space-y-3">
                        <span class="text-xs font-bold uppercase tracking-wider text-primary block">Full Legal Name Details *</span>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
                            <!-- Given / First Name -->
                            <div class="sm:col-span-4">
                                <label for="first_name" class="block text-[11px] font-bold text-dark/70 mb-1">Given / First Name *</label>
                                <input id="first_name" type="text" name="first_name" value="{{ old('first_name') }}" required
                                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                                       placeholder="Juan">
                                @error('first_name') <p class="mt-1 text-xs text-rose-600 font-semibold">{{ $message }}</p> @enderror
                            </div>

                            <!-- Middle Name -->
                            <div class="sm:col-span-3">
                                <label for="middle_name" class="block text-[11px] font-bold text-dark/70 mb-1">Middle Name</label>
                                <input id="middle_name" type="text" name="middle_name" value="{{ old('middle_name') }}"
                                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                                       placeholder="Dela Cruz">
                            </div>

                            <!-- Last Name / Surname -->
                            <div class="sm:col-span-3">
                                <label for="last_name" class="block text-[11px] font-bold text-dark/70 mb-1">Last Name / Surname *</label>
                                <input id="last_name" type="text" name="last_name" value="{{ old('last_name') }}" required
                                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                                       placeholder="Santos">
                                @error('last_name') <p class="mt-1 text-xs text-rose-600 font-semibold">{{ $message }}</p> @enderror
                            </div>

                            <!-- Suffix -->
                            <div class="sm:col-span-2">
                                <label for="suffix" class="block text-[11px] font-bold text-dark/70 mb-1">Suffix</label>
                                <select id="suffix" name="suffix" class="w-full px-2.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                                    <option value="">None</option>
                                    <option value="Jr." {{ old('suffix') === 'Jr.' ? 'selected' : '' }}>Jr.</option>
                                    <option value="Sr." {{ old('suffix') === 'Sr.' ? 'selected' : '' }}>Sr.</option>
                                    <option value="III" {{ old('suffix') === 'III' ? 'selected' : '' }}>III</option>
                                    <option value="IV" {{ old('suffix') === 'IV' ? 'selected' : '' }}>IV</option>
                                    <option value="V" {{ old('suffix') === 'V' ? 'selected' : '' }}>V</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Contact & Authentication Credentials -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Email Address -->
                        <div>
                            <label for="email" class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1">Email Address *</label>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" required
                                   class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                                   placeholder="you@example.com">
                            @error('email') <p class="mt-1 text-xs text-rose-600 font-semibold">{{ $message }}</p> @enderror
                        </div>

                        <!-- Contact Phone -->
                        <div>
                            <label for="phone" class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1">Phone / Mobile Number *</label>
                            <input id="phone" type="text" name="phone" value="{{ old('phone') }}" required
                                   class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                                   placeholder="+63 917 123 4567">
                            @error('phone') <p class="mt-1 text-xs text-rose-600 font-semibold">{{ $message }}</p> @enderror
                        </div>

                        <!-- Password -->
                        <div>
                            <label for="password" class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1">Password *</label>
                            <input id="password" type="password" name="password" required
                                   class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                                   placeholder="At least 8 characters">
                            @error('password') <p class="mt-1 text-xs text-rose-600 font-semibold">{{ $message }}</p> @enderror
                        </div>

                        <!-- Confirm Password -->
                        <div>
                            <label for="password_confirmation" class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1">Confirm Password *</label>
                            <input id="password_confirmation" type="password" name="password_confirmation" required
                                   class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                                   placeholder="Repeat password">
                        </div>
                    </div>

                    <!-- Address Information Section -->
                    <div class="p-4 rounded-2xl bg-gray-50 border border-gray-200 space-y-3">
                        <span class="text-xs font-bold uppercase tracking-wider text-primary block">Residential Address Information *</span>

                        <div class="space-y-3">
                            <div>
                                <label for="address_line" class="block text-[11px] font-bold text-dark/70 mb-1">Street Address / House No. / Barangay</label>
                                <input id="address_line" type="text" name="address_line" value="{{ old('address_line') }}"
                                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                                       placeholder="123 Rizal Street, Brgy. Central">
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                                <div>
                                    <label for="city" class="block text-[11px] font-bold text-dark/70 mb-1">City / Municipality</label>
                                    <input id="city" type="text" name="city" value="{{ old('city') }}"
                                           class="w-full px-3 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                                           placeholder="Manila">
                                </div>

                                <div>
                                    <label for="province" class="block text-[11px] font-bold text-dark/70 mb-1">Province / State</label>
                                    <input id="province" type="text" name="province" value="{{ old('province') }}"
                                           class="w-full px-3 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                                           placeholder="Metro Manila">
                                </div>

                                <div>
                                    <label for="postal_code" class="block text-[11px] font-bold text-dark/70 mb-1">Postal Code</label>
                                    <input id="postal_code" type="text" name="postal_code" value="{{ old('postal_code') }}"
                                           class="w-full px-3 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                                           placeholder="1000">
                                </div>

                                <div>
                                    <label for="country" class="block text-[11px] font-bold text-dark/70 mb-1">Country</label>
                                    <input id="country" type="text" name="country" value="{{ old('country', 'Philippines') }}"
                                           class="w-full px-3 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                                           placeholder="Philippines">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Nationality & Emergency Contact Section -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="nationality" class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1">Nationality & Citizenship</label>
                            <input id="nationality" type="text" name="nationality" value="{{ old('nationality', 'Filipino') }}"
                                   class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                                   placeholder="e.g. Filipino, American, Japanese">
                        </div>

                        <!-- Emergency Contact -->
                        <div class="sm:col-span-2 p-4 rounded-2xl bg-gray-50 border border-gray-200 space-y-3">
                            <span class="text-xs font-bold uppercase tracking-wider text-primary block">Emergency Contact Person</span>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <label for="emergency_contact_name" class="block text-[11px] font-bold text-dark/70 mb-1">Contact Name</label>
                                    <input id="emergency_contact_name" type="text" name="emergency_contact_name" value="{{ old('emergency_contact_name') }}"
                                           class="w-full px-3 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                                           placeholder="Parent / Spouse / Relative">
                                </div>

                                <div>
                                    <label for="emergency_contact_phone" class="block text-[11px] font-bold text-dark/70 mb-1">Contact Phone</label>
                                    <input id="emergency_contact_phone" type="text" name="emergency_contact_phone" value="{{ old('emergency_contact_phone') }}"
                                           class="w-full px-3 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                                           placeholder="+63 918 987 6543">
                                </div>

                                <div>
                                    <label for="emergency_contact_relationship" class="block text-[11px] font-bold text-dark/70 mb-1">Relationship</label>
                                    <input id="emergency_contact_relationship" type="text" name="emergency_contact_relationship" value="{{ old('emergency_contact_relationship') }}"
                                           class="w-full px-3 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                                           placeholder="Spouse, Mother, Brother">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 flex justify-end">
                        <button type="button" onclick="goToStep(2)" class="px-6 py-3 rounded-full bg-primary text-white font-heading font-bold text-xs hover:bg-primary-dark transition-all shadow-md flex items-center gap-2">
                            <span>Next: Passport & ID Photo Upload</span>
                            <i data-lucide="arrow-right" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>

                <!-- STEP 2: Passport Information & Government ID Photo Upload -->
                <div id="step-2" class="space-y-5 hidden">
                    <div class="border-b border-gray-100 pb-3">
                        <h2 class="font-heading text-lg font-bold text-dark">Step 2: Passport & Government ID Photo Upload</h2>
                        <p class="text-xs text-dark/50">Passport details, account category, and government ID document photo</p>
                    </div>

                    <!-- Category Selection -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-2">Account / Traveler Category *</label>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            @foreach(['Individual', 'Family', 'Corporate', 'Agency'] as $cat)
                                <label class="cursor-pointer">
                                    <input type="radio" name="account_category" value="{{ $cat }}" {{ old('account_category', 'Individual') === $cat ? 'checked' : '' }} class="peer sr-only">
                                    <div class="p-3 text-center rounded-2xl border border-gray-200 bg-gray-50 peer-checked:bg-primary/10 peer-checked:border-primary peer-checked:text-primary transition-all">
                                        <div class="text-xs font-bold">{{ $cat }}</div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <!-- Passport Information Group -->
                    <div class="p-4 rounded-2xl bg-gray-50 border border-gray-200 space-y-3">
                        <span class="text-xs font-bold uppercase tracking-wider text-primary block">Passport Records (Optional for Domestic, Recommended)</span>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label for="passport_number" class="block text-[11px] font-bold text-dark/70 mb-1">Passport Number</label>
                                <input id="passport_number" type="text" name="passport_number" value="{{ old('passport_number') }}"
                                       class="w-full px-3 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                                       placeholder="P1234567A">
                            </div>

                            <div>
                                <label for="passport_expiry" class="block text-[11px] font-bold text-dark/70 mb-1">Passport Expiry Date</label>
                                <input id="passport_expiry" type="date" name="passport_expiry" value="{{ old('passport_expiry') }}"
                                       class="w-full px-3 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>

                            <div>
                                <label for="passport_country" class="block text-[11px] font-bold text-dark/70 mb-1">Issuing Country</label>
                                <input id="passport_country" type="text" name="passport_country" value="{{ old('passport_country', 'Philippines') }}"
                                       class="w-full px-3 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                                       placeholder="Philippines">
                            </div>
                        </div>
                    </div>

                    <!-- Government ID Records & ID Photo Upload -->
                    <div class="p-5 rounded-2xl bg-gray-50 border border-gray-200 space-y-4">
                        <span class="text-xs font-bold uppercase tracking-wider text-primary block">Government-Issued Identification Records & ID Document Upload *</span>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="government_id_type" class="block text-[11px] font-bold text-dark/70 mb-1">Government ID Type</label>
                                <select id="government_id_type" name="government_id_type" class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                                    <option value="">-- Select Identification Type --</option>
                                    <option value="National ID / PhilSys" {{ old('government_id_type') === 'National ID / PhilSys' ? 'selected' : '' }}>National ID / PhilSys</option>
                                    <option value="Driver's License" {{ old('government_id_type') === "Driver's License" ? 'selected' : '' }}>Driver's License</option>
                                    <option value="UMID / SSS / GSIS" {{ old('government_id_type') === 'UMID / SSS / GSIS' ? 'selected' : '' }}>UMID / SSS / GSIS</option>
                                    <option value="Passport" {{ old('government_id_type') === 'Passport' ? 'selected' : '' }}>Passport</option>
                                    <option value="PRC ID" {{ old('government_id_type') === 'PRC ID' ? 'selected' : '' }}>PRC License ID</option>
                                    <option value="Voter's ID / Certification" {{ old('government_id_type') === "Voter's ID / Certification" ? 'selected' : '' }}>Voter's ID / Certification</option>
                                </select>
                            </div>

                            <div>
                                <label for="government_id_number" class="block text-[11px] font-bold text-dark/70 mb-1">ID Number / Reference Code</label>
                                <input id="government_id_number" type="text" name="government_id_number" value="{{ old('government_id_number') }}"
                                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                                       placeholder="1234-5678-9012-3456">
                            </div>
                        </div>

                        <!-- ID Photo Upload Input -->
                        <div class="pt-2 border-t border-gray-200">
                            <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Upload Government ID Photo / Scan *</label>
                            <div class="p-4 rounded-xl bg-white border border-gray-200 space-y-2">
                                <input type="file" name="government_id_photo" id="government_id_photo" accept="image/*,.pdf" onchange="previewIDPhoto(this)"
                                       class="block w-full text-xs text-dark/70 file:mr-3 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-accent file:text-dark hover:file:bg-accent-dark">
                                <p class="text-[10px] text-dark/50">Upload a clear photo or scan of your Government ID (JPG, PNG, WEBP, or PDF up to 5MB)</p>
                                <div id="id-photo-preview-box" class="hidden pt-2">
                                    <span class="text-[10px] text-emerald-700 font-bold flex items-center gap-1">
                                        <i data-lucide="check-circle" class="w-3.5 h-3.5"></i>
                                        <span>ID File selected successfully</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 flex items-center justify-between">
                        <button type="button" onclick="goToStep(1)" class="px-5 py-2.5 rounded-full bg-gray-100 text-dark font-bold text-xs hover:bg-gray-200">
                            ← Back
                        </button>
                        <button type="button" onclick="goToStep(3)" class="px-6 py-3 rounded-full bg-primary text-white font-heading font-bold text-xs hover:bg-primary-dark transition-all shadow-md flex items-center gap-2">
                            <span>Next: Profile & Signature</span>
                            <i data-lucide="arrow-right" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>

                <!-- STEP 3: Profile Upload & E-Signature -->
                <div id="step-3" class="space-y-5 hidden">
                    <div class="border-b border-gray-100 pb-3">
                        <h2 class="font-heading text-lg font-bold text-dark">Step 3: Profile Upload & E-Signature</h2>
                        <p class="text-xs text-dark/50">Upload profile photo and sign e-signature authorization (optional)</p>
                    </div>

                    <!-- Profile Upload Avatar -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-2">Profile Photo Upload (Optional)</label>
                        <div class="flex items-center gap-4 p-4 rounded-2xl bg-gray-50 border border-gray-200">
                            <div class="w-16 h-16 rounded-full bg-gray-200 border-2 border-primary/30 flex items-center justify-center overflow-hidden shrink-0" id="avatar-preview-box">
                                <i data-lucide="user" class="w-8 h-8 text-dark/40" id="avatar-icon"></i>
                                <img id="avatar-img-preview" class="w-full h-full object-cover hidden">
                            </div>
                            <div class="space-y-1">
                                <input type="file" name="profile_photo" id="profile_photo" accept="image/*" onchange="previewAvatar(this)" class="block w-full text-xs text-dark/70 file:mr-3 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-primary/10 file:text-primary hover:file:bg-primary hover:file:text-white">
                                <p class="text-[10px] text-dark/40">PNG, JPG, or WEBP up to 2MB</p>
                            </div>
                        </div>
                    </div>

                    <!-- Interactive E-Signature Canvas -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-bold uppercase tracking-wider text-dark/70">Digital E-Signature (Optional)</label>
                            <button type="button" onclick="clearSignature()" class="text-[11px] text-rose-600 font-bold hover:underline">Clear Signature</button>
                        </div>
                        <div class="rounded-2xl border-2 border-dashed border-gray-300 bg-gray-50 p-2 text-center">
                            <canvas id="signature-pad" width="500" height="150" class="w-full h-36 bg-white rounded-xl cursor-crosshair border border-gray-200 shadow-inner"></canvas>
                            <p class="text-[10px] text-dark/40 mt-1">Draw your signature inside the box using mouse or touch</p>
                        </div>
                    </div>

                    <div class="pt-4 flex items-center justify-between border-t border-gray-100">
                        <button type="button" onclick="goToStep(2)" class="px-5 py-2.5 rounded-full bg-gray-100 text-dark font-bold text-xs hover:bg-gray-200">
                            ← Back
                        </button>
                        <button type="submit" onclick="prepareSubmission()" class="px-8 py-3.5 rounded-full bg-emerald-600 text-white font-heading font-bold text-sm hover:bg-emerald-700 transition-all shadow-lg flex items-center gap-2">
                            <span>Complete Registration & Account</span>
                            <i data-lucide="check-circle" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>
            </form>

            <div class="mt-6 pt-6 border-t border-gray-100 text-center text-xs text-dark/60">
                Already have an account? 
                <a href="{{ route('login') }}" class="text-primary font-bold hover:underline">Sign In</a>
            </div>
        </div>
    </div>
</section>

<!-- Canvas Signature Script & Multi-Step Logic -->
<script>
    let currentStep = 1;

    function goToStep(step) {
        // Hide all steps
        document.getElementById('step-1').classList.add('hidden');
        document.getElementById('step-2').classList.add('hidden');
        document.getElementById('step-3').classList.add('hidden');

        // Show targeted step
        document.getElementById('step-' + step).classList.remove('hidden');

        // Update progress indicators
        const progressBar = document.getElementById('progress-bar');
        const btn1 = document.getElementById('step-btn-1');
        const btn2 = document.getElementById('step-btn-2');
        const btn3 = document.getElementById('step-btn-3');
        const lbl1 = document.getElementById('step-lbl-1');
        const lbl2 = document.getElementById('step-lbl-2');
        const lbl3 = document.getElementById('step-lbl-3');

        if (step === 1) {
            progressBar.style.width = '0%';
            btn1.className = "w-10 h-10 rounded-full bg-primary text-white font-bold text-sm flex items-center justify-center relative z-10 shadow-md transition-all";
            btn2.className = "w-10 h-10 rounded-full bg-gray-100 text-dark/50 font-bold text-sm flex items-center justify-center relative z-10 transition-all";
            btn3.className = "w-10 h-10 rounded-full bg-gray-100 text-dark/50 font-bold text-sm flex items-center justify-center relative z-10 transition-all";
            lbl1.className = "text-primary font-bold";
            lbl2.className = "";
            lbl3.className = "";
        } else if (step === 2) {
            progressBar.style.width = '50%';
            btn1.className = "w-10 h-10 rounded-full bg-emerald-500 text-white font-bold text-sm flex items-center justify-center relative z-10 shadow-md transition-all";
            btn2.className = "w-10 h-10 rounded-full bg-primary text-white font-bold text-sm flex items-center justify-center relative z-10 shadow-md transition-all";
            btn3.className = "w-10 h-10 rounded-full bg-gray-100 text-dark/50 font-bold text-sm flex items-center justify-center relative z-10 transition-all";
            lbl1.className = "text-emerald-600 font-bold";
            lbl2.className = "text-primary font-bold";
            lbl3.className = "";
        } else if (step === 3) {
            progressBar.style.width = '100%';
            btn1.className = "w-10 h-10 rounded-full bg-emerald-500 text-white font-bold text-sm flex items-center justify-center relative z-10 shadow-md transition-all";
            btn2.className = "w-10 h-10 rounded-full bg-emerald-500 text-white font-bold text-sm flex items-center justify-center relative z-10 shadow-md transition-all";
            btn3.className = "w-10 h-10 rounded-full bg-primary text-white font-bold text-sm flex items-center justify-center relative z-10 shadow-md transition-all";
            lbl1.className = "text-emerald-600 font-bold";
            lbl2.className = "text-emerald-600 font-bold";
            lbl3.className = "text-primary font-bold";
            initSignaturePad();
        }

        currentStep = step;
        window.scrollTo({ top: 100, behavior: 'smooth' });
    }

    function previewAvatar(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('avatar-icon').classList.add('hidden');
                const img = document.getElementById('avatar-img-preview');
                img.src = e.target.result;
                img.classList.remove('hidden');
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    function previewIDPhoto(input) {
        if (input.files && input.files[0]) {
            document.getElementById('id-photo-preview-box').classList.remove('hidden');
        }
    }

    // Canvas E-Signature Pad Initialization
    let canvas, ctx, isDrawing = false;

    function initSignaturePad() {
        canvas = document.getElementById('signature-pad');
        if (!canvas) return;
        ctx = canvas.getContext('2d');

        // Set line style
        ctx.strokeStyle = '#0f172a';
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';

        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDrawing);
        canvas.addEventListener('mouseleave', stopDrawing);

        // Touch events for mobile support
        canvas.addEventListener('touchstart', (e) => {
            const touch = e.touches[0];
            const rect = canvas.getBoundingClientRect();
            ctx.beginPath();
            ctx.moveTo(touch.clientX - rect.left, touch.clientY - rect.top);
            isDrawing = true;
        });

        canvas.addEventListener('touchmove', (e) => {
            if (!isDrawing) return;
            e.preventDefault();
            const touch = e.touches[0];
            const rect = canvas.getBoundingClientRect();
            ctx.lineTo(touch.clientX - rect.left, touch.clientY - rect.top);
            ctx.stroke();
        });

        canvas.addEventListener('touchend', stopDrawing);
    }

    function startDrawing(e) {
        isDrawing = true;
        const rect = canvas.getBoundingClientRect();
        ctx.beginPath();
        ctx.moveTo(e.clientX - rect.left, e.clientY - rect.top);
    }

    function draw(e) {
        if (!isDrawing) return;
        const rect = canvas.getBoundingClientRect();
        ctx.lineTo(e.clientX - rect.left, e.clientY - rect.top);
        ctx.stroke();
    }

    function stopDrawing() {
        if (isDrawing) {
            isDrawing = false;
            ctx.closePath();
            saveSignatureData();
        }
    }

    function clearSignature() {
        if (ctx && canvas) {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            document.getElementById('signature-input').value = '';
        }
    }

    function saveSignatureData() {
        if (canvas) {
            const dataUrl = canvas.toDataURL('image/png');
            document.getElementById('signature-input').value = dataUrl;
        }
    }

    function prepareSubmission() {
        saveSignatureData();
    }
</script>
@endsection
