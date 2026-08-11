<?php

namespace App\Http\Controllers;

class BlogController extends Controller
{
    public function show(string $slug)
    {
        $articles = [
            'travel-tips' => [
                'title' => 'Best Places to Visit in 2026',
                'image' => 'https://images.unsplash.com/photo-1488646953014-85cb44e25828?w=1200&q=80',
                'content' => 'Discover the top destinations that should be on your travel bucket list this year...',
            ],
        ];

        $article = $articles[$slug] ?? null;

        if (! $article) {
            abort(404);
        }

        return view('blog.show', ['article' => $article, 'slug' => $slug]);
    }
}
