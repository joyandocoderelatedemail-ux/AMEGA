<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class AdminTestimonialController extends Controller
{
    public function index()
    {
        $testimonials = Testimonial::latest()->get();

        return view('admin.testimonials.index', compact('testimonials'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'comment' => 'required|string',
            'rating' => 'required|integer|min:1|max:5',
        ]);

        $testimonial = Testimonial::create($validated);

        ActivityLogger::log('Testimonials', 'CREATE', "Added client review testimonial from '{$testimonial->name}'");

        return redirect()->route('admin.testimonials.index')->with('success', 'Testimonial added successfully!');
    }

    public function destroy(Testimonial $testimonial)
    {
        $name = $testimonial->name;
        $testimonial->delete();

        ActivityLogger::log('Testimonials', 'DELETE', "Deleted client review testimonial from '{$name}'");

        return redirect()->route('admin.testimonials.index')->with('success', 'Testimonial deleted successfully!');
    }
}
