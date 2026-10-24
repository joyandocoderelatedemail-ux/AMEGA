<?php

namespace App\Http\Controllers;

use App\Mail\ContactFormMail;
use App\Models\Inquiry;
use App\Support\Countries;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ContactController extends Controller
{
    public function submit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'account_category' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            // Digits only, as the form allows: 7 to 15 of them (local or international without the +).
            'phone' => ['required', 'regex:/^[0-9]{7,15}$/'],
            'number_of_pax' => 'required|integer|min:1|max:500',
            'nationality' => ['required', 'string', Rule::in(Countries::all())],
            'message' => 'required|string|max:5000',
        ], [
            'phone.required' => 'Please enter your phone or mobile number.',
            'phone.regex' => 'The phone number must be numbers only (7 to 15 digits).',
        ]);

        // Sent from the page in the background: answer in JSON, so the visitor stays where they are.
        if ($validator->fails() && $request->expectsJson()) {
            return response()->json(['message' => 'Please check the form.', 'errors' => $validator->errors()], 422);
        }

        // Land back on the form itself, not the top of the page, so the errors are seen.
        if ($validator->fails()) {
            return back()->withFragment('contact')->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        $fullName = trim(($validated['first_name'] ?? '').' '.($validated['last_name'] ?? ''));
        if (empty($fullName)) {
            $fullName = $validated['name'] ?? 'Guest Traveler';
        }

        // Save to MySQL database
        Inquiry::create([
            'name' => $fullName,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'number_of_pax' => $validated['number_of_pax'],
            'nationality' => $validated['nationality'],
            'message' => $validated['message'],
            'service_requested' => $validated['account_category'] ?? null,
            'status' => 'pending',
        ]);

        try {
            Mail::to(config('mail.from.address', 'info@amegatravel.com'))->send(new ContactFormMail([
                'name' => $fullName,
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'category' => $validated['account_category'] ?? null,
                'number_of_pax' => $validated['number_of_pax'],
                'nationality' => $validated['nationality'],
                'message' => $validated['message'],
            ]));
        } catch (\Throwable $e) {
            // Mail fallback gracefully handled
        }

        $thanks = 'Thank you for your message! Our travel agents will get back to you shortly.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $thanks]);
        }

        return back()->withFragment('contact')->with('success', $thanks);
    }
}
