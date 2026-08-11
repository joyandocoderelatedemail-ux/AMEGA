<?php

namespace App\Http\Controllers;

use App\Models\Destination;
use App\Models\GalleryItem;
use App\Models\Service;
use App\Models\Testimonial;
use App\Models\TravelPackage;

class PageController extends Controller
{
    public function home()
    {
        $localDestinations = Destination::where('type', 'domestic')->where('is_featured', true)->get();
        $intlDestinations = Destination::where('type', 'international')->where('is_featured', true)->get();
        $travelPackages = TravelPackage::where('is_featured', true)->get();
        $services = Service::where('is_active', true)->orderBy('order', 'asc')->get();
        $testimonials = Testimonial::where('is_published', true)->get();
        $galleryItems = GalleryItem::orderBy('order', 'asc')->get();

        return view('home', compact(
            'localDestinations',
            'intlDestinations',
            'travelPackages',
            'services',
            'testimonials',
            'galleryItems'
        ));
    }

    public function services()
    {
        $services = Service::where('is_active', true)->orderBy('order', 'asc')->get();

        return view('pages.services', compact('services'));
    }

    public function tours()
    {
        $localDestinations = Destination::where('type', 'domestic')->get();
        $intlDestinations = Destination::where('type', 'international')->get();
        $travelPackages = TravelPackage::all();

        return view('pages.tours', compact('localDestinations', 'intlDestinations', 'travelPackages'));
    }

    public function about()
    {
        return view('pages.about');
    }

    public function contact()
    {
        return view('pages.contact');
    }

    public function gallery()
    {
        $galleryItems = GalleryItem::orderBy('order', 'asc')->get();

        return view('pages.gallery', compact('galleryItems'));
    }

    public function whyUs()
    {
        return view('pages.why-us');
    }

    public function testimonials()
    {
        $testimonials = Testimonial::where('is_published', true)->get();

        return view('pages.testimonials', compact('testimonials'));
    }
}
