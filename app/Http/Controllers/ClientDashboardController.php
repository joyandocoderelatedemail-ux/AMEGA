<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClientDashboardController extends Controller
{
    /**
     * Display client dashboard with personal bookings and profile.
     */
    public function index()
    {
        $user = Auth::user();
        $bookings = $user->bookings()->with('travelPackage')->latest()->get();

        return view('client.dashboard', compact('user', 'bookings'));
    }

    /**
     * Display client profile details in read-only view.
     */
    public function showProfile()
    {
        $user = Auth::user();

        return view('client.profile', compact('user'));
    }

    /**
     * Update client profile information (phone, address).
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
        ]);

        $user->update($validated);

        return back()->with('success', 'Your profile details have been updated successfully!');
    }
}
