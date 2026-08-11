<?php

namespace Database\Seeders;

use App\Models\Testimonial;
use Illuminate\Database\Seeder;

class TestimonialSeeder extends Seeder
{
    public function run(): void
    {
        $testimonials = [
            [
                'name' => 'Sarah & James M.',
                'location' => 'Tokyo, Japan',
                'comment' => 'AMEGA made our Japan trip absolutely magical. From the bullet trains to the traditional ryokan, every detail was perfectly planned. We couldn\'t have asked for a better experience!',
                'rating' => 5,
                'is_published' => true,
            ],
            [
                'name' => 'Michael T.',
                'location' => 'Interlaken, Switzerland',
                'comment' => 'The Swiss Alpine tour exceeded every expectation. The views, the chalets, the service — everything was world-class. Worth every penny!',
                'rating' => 5,
                'is_published' => true,
            ],
            [
                'name' => 'Priya K.',
                'location' => 'Bali, Indonesia',
                'comment' => 'Our honeymoon package was beyond our dreams. Private villa, sunset cruise, spa treatments — everything was romantic and perfectly organized.',
                'rating' => 5,
                'is_published' => true,
            ],
            [
                'name' => 'David L.',
                'location' => 'El Nido, Palawan',
                'comment' => 'AMEGA helped us discover the beauty of our own country. El Nido was breathtaking, and the tour was seamless from start to finish.',
                'rating' => 5,
                'is_published' => true,
            ],
            [
                'name' => 'Emma R.',
                'location' => 'Seoul, South Korea',
                'comment' => 'Korea with AMEGA was an incredible experience. The guides were knowledgeable, the itinerary was perfect, and we made memories for life.',
                'rating' => 5,
                'is_published' => true,
            ],
        ];

        foreach ($testimonials as $t) {
            Testimonial::updateOrCreate(
                ['name' => $t['name'], 'location' => $t['location']],
                $t
            );
        }
    }
}
