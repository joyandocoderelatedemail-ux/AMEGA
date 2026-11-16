<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use App\Services\ActivityLogger;
use App\Support\TestimonialPhotoStorage;
use Illuminate\Http\Request;
use RuntimeException;

class AdminTestimonialController extends Controller
{
    public function index()
    {
        $testimonials = Testimonial::orderByDesc('reviewed_on')->latest()->get();

        return view('admin.testimonials.index', compact('testimonials'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'comment' => 'nullable|string',
            'rating' => 'nullable|integer|min:1|max:5',
            'reviewed_on' => 'required|date|before_or_equal:today',
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'photo.image' => 'The photo must be an image file.',
            'photo.mimes' => 'Use a JPG, PNG or WebP photo.',
            'photo.max' => 'The photo is larger than 5 MB.',
            'photo.uploaded' => 'The photo could not be uploaded. Check that it is under 5 MB and try again.',
        ]);

        unset($validated['photo']);

        if ($request->hasFile('photo')) {
            try {
                $validated['avatar'] = TestimonialPhotoStorage::store($request->file('photo'));
            } catch (RuntimeException) {
                return back()->withInput()->withErrors(['photo' => 'The photo could not be uploaded. Please try again.']);
            }
        }

        $testimonial = Testimonial::create($validated);

        ActivityLogger::log('Testimonials', 'CREATE', "Added client review testimonial from '{$testimonial->name}'");

        return redirect()->route('admin.testimonials.index')->with('success', 'Testimonial added successfully!');
    }

    public function destroy(Testimonial $testimonial)
    {
        $name = $testimonial->name;
        $avatar = $testimonial->avatar;
        $testimonial->delete();
        TestimonialPhotoStorage::delete($avatar);

        ActivityLogger::log('Testimonials', 'DELETE', "Deleted client review testimonial from '{$name}'");

        return redirect()->route('admin.testimonials.index')->with('success', 'Testimonial deleted successfully!');
    }
}
