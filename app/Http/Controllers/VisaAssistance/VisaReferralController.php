<?php

namespace App\Http\Controllers\VisaAssistance;

use App\Http\Controllers\Controller;
use App\Support\VisaReferrals;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ticketing bookings whose passengers need visa or passport help, handed to the Visa Assistance desk.
 */
class VisaReferralController extends Controller
{
    public function index(): View
    {
        $referrals = VisaReferrals::query()->with(['passengers', 'createdBy:id,name'])->latest()->get();
        $newIds = $referrals->whereNull('visa_referral_seen_at')->pluck('id')->all();

        // Opening the list clears the bell.
        VisaReferrals::markSeen();

        return view('visa-assistance.referrals', compact('referrals', 'newIds'));
    }

    /** The desk bell's poll. */
    public function notifications(Request $request): JsonResponse
    {
        return response()->json(VisaReferrals::bell($request->user()));
    }
}
