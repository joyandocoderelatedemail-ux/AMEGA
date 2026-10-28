<?php

namespace App\Http\Controllers;

use App\Models\TravelPackage;
use Illuminate\Http\Response;

/**
 * sitemap.xml: the public pages and every tour package, so search engines find them all.
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $pages = collect(['home', 'services', 'tours', 'packages.index', 'immigration-pricing', 'about', 'why-us', 'gallery', 'testimonials', 'faq', 'contact', 'privacy'])
            ->map(fn (string $route) => ['loc' => route($route), 'lastmod' => null]);

        $packages = TravelPackage::query()->get(['id', 'updated_at'])
            ->map(fn (TravelPackage $package) => ['loc' => route('packages.show', $package), 'lastmod' => $package->updated_at?->toAtomString()]);

        return response()
            ->view('seo.sitemap', ['urls' => $pages->concat($packages)])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
