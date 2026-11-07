<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Services\ActivityLogger;
use App\Support\DateRange;
use Illuminate\Http\Request;

class AdminInquiryController extends Controller
{
    public function index(Request $request)
    {
        $inquiries = DateRange::fromRequest($request)->apply(Inquiry::latest())->paginate(15)->withQueryString();

        // Opening the inbox reads what it shows: those stop counting on the badges, and are
        // marked New on this visit.
        $newIds = $inquiries->getCollection()->whereNull('read_at')->pluck('id')->all();
        if ($newIds !== []) {
            Inquiry::whereIn('id', $newIds)->update(['read_at' => now()]);
        }

        return view('admin.inquiries.index', compact('inquiries', 'newIds'));
    }

    /**
     * Follow-up on an inquiry: whether the client has been contacted, and the remarks.
     * Ticking Contacted records who did it and when; unticking clears both.
     */
    public function update(Request $request, Inquiry $inquiry)
    {
        $validated = $request->validate([
            'remarks' => 'nullable|string|max:2000',
        ]);

        $contacted = $request->boolean('contacted');

        $attributes = ['remarks' => filled($validated['remarks'] ?? null) ? trim($validated['remarks']) : null];

        if ($contacted && ! $inquiry->contacted_at) {
            $attributes += ['contacted_at' => now(), 'contacted_by' => $request->user()->id];
        } elseif (! $contacted) {
            $attributes += ['contacted_at' => null, 'contacted_by' => null];
        }

        $inquiry->update($attributes);

        ActivityLogger::log('Inquiries', 'UPDATE', "Follow-up on inquiry from '{$inquiry->name}': ".($contacted ? 'contacted' : 'not yet contacted'), ['inquiry_id' => $inquiry->id]);

        return back()->with('success', 'Follow-up saved.');
    }

    public function destroy(Inquiry $inquiry)
    {
        $name = $inquiry->name;
        $inquiry->delete();

        ActivityLogger::log('Inquiries', 'DELETE', "Deleted customer inquiry from '{$name}'");

        return redirect()->route('admin.inquiries.index')
            ->with('success', 'Inquiry deleted successfully!');
    }
}
