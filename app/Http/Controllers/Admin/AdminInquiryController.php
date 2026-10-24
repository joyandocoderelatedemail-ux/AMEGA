<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Services\ActivityLogger;

class AdminInquiryController extends Controller
{
    public function index()
    {
        $inquiries = Inquiry::latest()->paginate(15);

        // Opening the inbox reads what it shows: those stop counting on the badges, and are
        // marked New on this visit.
        $newIds = $inquiries->getCollection()->whereNull('read_at')->pluck('id')->all();
        if ($newIds !== []) {
            Inquiry::whereIn('id', $newIds)->update(['read_at' => now()]);
        }

        return view('admin.inquiries.index', compact('inquiries', 'newIds'));
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
