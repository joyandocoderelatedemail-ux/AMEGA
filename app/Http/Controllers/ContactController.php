<?php

namespace App\Http\Controllers;

use App\Mail\ContactFormMail;
use App\Models\Inquiry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function submit(Request $request)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'account_category' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:255',
            'message' => 'required|string|max:5000',
        ]);

        $fullName = trim(($validated['first_name'] ?? '').' '.($validated['last_name'] ?? ''));
        if (empty($fullName)) {
            $fullName = $validated['name'] ?? 'Guest Traveler';
        }

        $category = $validated['account_category'] ?? 'Individual';
        $fullMessage = "[Category: {$category}]\n".$validated['message'];

        // Save to MySQL database
        Inquiry::create([
            'name' => $fullName,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'message' => $fullMessage,
            'status' => 'pending',
        ]);

        try {
            Mail::to(config('mail.from.address', 'info@amegatravel.com'))->send(new ContactFormMail([
                'name' => $fullName,
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'message' => $fullMessage,
            ]));
        } catch (\Throwable $e) {
            // Mail fallback gracefully handled
        }

        return redirect()->to('#contact')->with('success', 'Thank you for your message! Our travel agents will get back to you shortly.');
    }
}
