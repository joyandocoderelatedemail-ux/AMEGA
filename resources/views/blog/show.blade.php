@extends('layouts.app')

@section('content')
<section class="pt-32 pb-24 bg-white">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <a href="{{ url('/#blog') }}" class="inline-flex items-center text-primary font-semibold text-sm mb-8 hover:text-primary-dark transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 12H5m7-7l-7 7 7 7"/></svg>
            Back to Blog
        </a>

        <div class="rounded-2xl overflow-hidden shadow-sm border border-gray-100 mb-8">
            <img src="{{ $article['image'] }}" alt="{{ $article['title'] }}" class="w-full h-[400px] object-cover">
        </div>

        <h1 class="font-heading text-4xl font-bold text-dark mb-6">{{ $article['title'] }}</h1>

        <div class="prose prose-lg max-w-none text-dark/70">
            <p>{{ $article['content'] }}</p>
            <p class="mt-4">This article is being updated with more detailed content. Check back soon for the full guide!</p>
        </div>
    </div>
</section>
@endsection
