<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Display the client login view.
     */
    public function showClientLogin()
    {
        if (Auth::check()) {
            return Auth::user()->isStaff()
                ? redirect()->route('admin.dashboard')
                : redirect()->route('home');
        }

        return view('auth.login');
    }

    public function showLogin()
    {
        return $this->showClientLogin();
    }

    /**
     * Handle client/staff login authentication request.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();
            $user = Auth::user();

            if ($user->isStaff()) {
                ActivityLogger::log('Auth', 'LOGIN', "Staff member {$user->name} logged into Staff Portal");

                return redirect()->intended(route('admin.dashboard'))
                    ->with('success', 'Welcome back to Staff Portal, '.$user->name.'!');
            }

            return redirect()->intended(route('home'))
                ->with('success', 'Welcome back, '.$user->name.'!');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    /**
     * Display the client registration view.
     */
    public function showRegister()
    {
        if (Auth::check()) {
            return redirect()->route('home');
        }

        return view('auth.register');
    }

    /**
     * Handle client user registration request.
     */
    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            // Part 1: Personal & Name Split & Address & Emergency Contact
            'first_name' => ['nullable', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'nationality' => ['nullable', 'string', 'max:100'],

            // Address Fields
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],

            // Emergency Contact
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:100'],

            // Part 2: Passport & Government ID & Photo & Category
            'passport_number' => ['nullable', 'string', 'max:100'],
            'passport_expiry' => ['nullable', 'date'],
            'passport_country' => ['nullable', 'string', 'max:100'],
            'government_id_type' => ['nullable', 'string', 'max:100'],
            'government_id_number' => ['nullable', 'string', 'max:100'],
            'government_id_photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:5120'],
            'account_category' => ['nullable', 'string', 'in:Individual,Family,Corporate,Agency'],

            // Part 3: Uploads & E-Signature
            'profile_photo' => ['nullable', 'image', 'max:2048'],
            'signature' => ['nullable', 'string'],
        ]);

        // Upload Profile Photo
        $photoPath = null;
        if ($request->hasFile('profile_photo')) {
            $photoPath = $request->file('profile_photo')->store('profiles', 'public');
        }

        // Upload Government ID Photo
        $idPhotoPath = null;
        if ($request->hasFile('government_id_photo')) {
            $idPhotoPath = $request->file('government_id_photo')->store('ids', 'public');
        }

        // Compute Full Name
        $firstName = $validated['first_name'] ?? '';
        $middleName = $validated['middle_name'] ?? '';
        $lastName = $validated['last_name'] ?? '';
        $suffix = $validated['suffix'] ?? '';

        $computedFullName = trim(implode(' ', array_filter([$firstName, $middleName, $lastName, $suffix])));
        $finalName = ! empty($computedFullName) ? $computedFullName : ($validated['name'] ?? 'Client User');

        // Compute Full Address
        $fullAddress = implode(', ', array_filter([
            $validated['address_line'] ?? null,
            $validated['city'] ?? null,
            $validated['province'] ?? null,
            $validated['postal_code'] ?? null,
            $validated['country'] ?? null,
        ]));

        $user = User::create([
            'name' => $finalName,
            'first_name' => $firstName ?: null,
            'middle_name' => $middleName ?: null,
            'last_name' => $lastName ?: null,
            'suffix' => $suffix ?: null,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'address' => ! empty($fullAddress) ? $fullAddress : null,
            'address_line' => $validated['address_line'] ?? null,
            'city' => $validated['city'] ?? null,
            'province' => $validated['province'] ?? null,
            'postal_code' => $validated['postal_code'] ?? null,
            'country' => $validated['country'] ?? 'Philippines',
            'password' => Hash::make($validated['password']),
            'role' => 'client',
            'nationality' => $validated['nationality'] ?? null,
            'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
            'emergency_contact_relationship' => $validated['emergency_contact_relationship'] ?? null,
            'passport_number' => $validated['passport_number'] ?? null,
            'passport_expiry' => $validated['passport_expiry'] ?? null,
            'passport_country' => $validated['passport_country'] ?? null,
            'government_id_type' => $validated['government_id_type'] ?? null,
            'government_id_number' => $validated['government_id_number'] ?? null,
            'government_id_photo' => $idPhotoPath,
            'account_category' => $validated['account_category'] ?? 'Individual',
            'profile_photo' => $photoPath,
            'signature' => $validated['signature'] ?? null,
        ]);

        Auth::login($user);

        return redirect()->route('home')
            ->with('success', 'Account created successfully! Welcome to AMEGA Travel & Tours.');
    }

    /**
     * Display the dedicated Agent portal login view.
     */
    public function showAgentLogin()
    {
        if (Auth::check() && Auth::user()->isStaff()) {
            return redirect()->route('admin.dashboard');
        }

        return view('auth.agent-login');
    }

    /**
     * Handle Agent portal authentication request.
     */
    public function agentLogin(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $user = Auth::user();

            if (! $user->isStaff()) {
                Auth::logout();

                return back()->withErrors([
                    'email' => 'Access denied. You do not have travel agent credentials.',
                ])->onlyInput('email');
            }

            $request->session()->regenerate();
            ActivityLogger::log('Auth', 'LOGIN', "Agent {$user->name} logged in via Agent Portal");

            return redirect()->intended(route('admin.dashboard'))
                ->with('success', 'Agent portal access granted! Welcome back, '.$user->name.'.');
        }

        return back()->withErrors([
            'email' => 'Invalid travel agent credentials.',
        ])->onlyInput('email');
    }

    /**
     * Display the admin login view.
     */
    public function showAdminLogin()
    {
        if (Auth::check() && Auth::user()->isStaff()) {
            return redirect()->route('admin.dashboard');
        }

        return view('auth.admin-login');
    }

    /**
     * Handle admin & agent staff login authentication request.
     */
    public function adminLogin(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $user = Auth::user();

            if (! $user->isStaff()) {
                Auth::logout();

                return back()->withErrors([
                    'email' => 'Access denied. Only Agents and Administrators may log in.',
                ])->onlyInput('email');
            }

            $request->session()->regenerate();

            return redirect()->intended(route('admin.dashboard'))
                ->with('success', 'Staff portal access granted.');
        }

        return back()->withErrors([
            'email' => 'Invalid administrator credentials.',
        ])->onlyInput('email');
    }

    /**
     * Log the user out of the application.
     */
    public function logout(Request $request): RedirectResponse
    {
        if (Auth::check()) {
            $user = Auth::user();
            ActivityLogger::log('Auth', 'LOGOUT', "User {$user->name} logged out");
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('info', 'You have been logged out.');
    }
}
