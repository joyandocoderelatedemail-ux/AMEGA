<?php

namespace Database\Seeders;

use App\Models\Destination;
use Illuminate\Database\Seeder;

class DestinationSeeder extends Seeder
{
    public function run(): void
    {
        $destinations = [
            // Domestic Destinations
            [
                'name' => 'El Nido Paradise',
                'location' => 'Palawan',
                'description' => 'Crystal lagoons, secret beaches, and towering limestone cliffs. Official 2026 AMEGA package.',
                'image' => 'newassets/2026-2027 DOMESTIC/2026 AMEGA EL NIDO NEW.jpg',
                'starting_price' => '₱15,000',
                'type' => 'domestic',
                'is_featured' => true,
            ],
            [
                'name' => 'Boracay White Sand',
                'location' => 'Aklan',
                'description' => 'Powder-soft white sand, electric sunsets, and vibrant island nightlife.',
                'image' => 'newassets/2026-2027 DOMESTIC/2026 AMEGA BORACAY  NEW.jpg',
                'starting_price' => '₱12,000',
                'type' => 'domestic',
                'is_featured' => true,
            ],
            [
                'name' => 'Siargao Island Escape',
                'location' => 'Surigao del Norte',
                'description' => 'World-class waves, palm-fringed roads, and ultimate island life experience.',
                'image' => 'newassets/2026-2027 DOMESTIC/2026 AMEGA SIARGAO NEW.jpg',
                'starting_price' => '₱14,000',
                'type' => 'domestic',
                'is_featured' => true,
            ],
            [
                'name' => 'Batanes Heritage',
                'location' => 'Batanes Islands',
                'description' => 'Rolling green hills, dramatic lighthouses, and serene sea cliffs.',
                'image' => 'newassets/2026-2027 DOMESTIC/2026 AMEGA BATANES NEW.jpg',
                'starting_price' => '₱18,500',
                'type' => 'domestic',
                'is_featured' => true,
            ],
            [
                'name' => 'Iloilo & Guimaras',
                'location' => 'Western Visayas',
                'description' => 'Historic heritage mansions, mango plantations, and rich culinary culture.',
                'image' => 'newassets/2026-2027 DOMESTIC/2026 AMEGA ILOILO NEW.jpg',
                'starting_price' => '₱11,800',
                'type' => 'domestic',
                'is_featured' => true,
            ],
            [
                'name' => 'Mt. Pinatubo Adventure',
                'location' => 'Central Luzon',
                'description' => 'Conquer the crater lake volcano with 4x4 4WD trek and natural spa hot springs.',
                'image' => 'newassets/2026 LOCAL TOURS/AMEGA LOCAL TOURS/2026 AMEGA PINATUBO NEW.jpg',
                'starting_price' => '₱3,800',
                'type' => 'domestic',
                'is_featured' => true,
            ],

            // International Destinations
            [
                'name' => 'Tokyo',
                'location' => 'Japan',
                'description' => 'Ancient temples meet neon-lit streets. Savor ramen at midnight and watch cherry blossoms fall.',
                'image' => 'images/international/pic-176.jpg',
                'starting_price' => '$2,499',
                'type' => 'international',
                'is_featured' => true,
            ],
            [
                'name' => 'Seoul',
                'location' => 'South Korea',
                'description' => 'K-culture, street food, and futuristic skylines. Dive into the heart of Korean excitement.',
                'image' => 'images/international/pic-180.jpg',
                'starting_price' => '$2,299',
                'type' => 'international',
                'is_featured' => true,
            ],
            [
                'name' => 'Interlaken',
                'location' => 'Switzerland',
                'description' => 'Crisp Alpine air, turquoise lakes, and snow-capped peaks. Step into a living postcard.',
                'image' => 'images/international/pic-190.jpg',
                'starting_price' => '$3,299',
                'type' => 'international',
                'is_featured' => true,
            ],
            [
                'name' => 'Paris',
                'location' => 'France',
                'description' => 'Croissants at sunrise, art at every corner, and that certain je ne sais quoi. Fall in love with Paris.',
                'image' => 'images/international/pic-195.jpg',
                'starting_price' => '$2,199',
                'type' => 'international',
                'is_featured' => true,
            ],
            [
                'name' => 'Bali',
                'location' => 'Indonesia',
                'description' => 'Tropical paradise with ancient temples, rice terraces, and spiritual wellness retreats.',
                'image' => 'images/international/pic-200.jpg',
                'starting_price' => '$1,899',
                'type' => 'international',
                'is_featured' => true,
            ],
            [
                'name' => 'Dubai',
                'location' => 'UAE',
                'description' => 'Ultra-modern architecture, luxury shopping, and desert adventures in the city of gold.',
                'image' => 'images/international/pic-205.jpg',
                'starting_price' => '$2,899',
                'type' => 'international',
                'is_featured' => true,
            ],
        ];

        foreach ($destinations as $dest) {
            Destination::updateOrCreate(
                ['name' => $dest['name']],
                $dest
            );
        }
    }
}
