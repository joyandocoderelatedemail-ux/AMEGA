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

        return view('admin.inquiries.index', compact('inquiries'));
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
