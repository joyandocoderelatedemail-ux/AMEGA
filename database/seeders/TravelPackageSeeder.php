<?php

namespace Database\Seeders;

use App\Models\Destination;
use App\Models\TravelPackage;
use Illuminate\Database\Seeder;

class TravelPackageSeeder extends Seeder
{
    public function run(): void
    {
        $tokyo = Destination::where('name', 'Tokyo')->first();
        $seoul = Destination::where('name', 'Seoul')->first();
        $dubai = Destination::where('name', 'Dubai')->first();
        $bali = Destination::where('name', 'Bali')->first();
        $boracay = Destination::where('name', 'Boracay')->first();
        $palawan = Destination::where('name', 'Palawan')->first();

        $defaultInclusions = "• Roundtrip Economy Airfare & Airport Taxes\n• 4/5-Star Hotel Accommodations with Daily Breakfast\n• Private Airport & Hotel Transfers\n• Fully Guided City Sightseeing Tours & Entrance Fees\n• English Speaking Professional Tour Guide\n• Complimentary Travel Insurance";
        $defaultExclusions = "• Personal Expenses & Room Service\n• Optional Tours and Gratuities\n• Single Occupancy Supplement\n• Philippine Travel Tax (₱1,620)";

        $packages = [
            [
                'destination_id' => $boracay?->id,
                'title' => 'Boracay Island Beach Getaway',
                'duration' => '4 Days / 3 Nights',
                'price' => '₱14,999',
                'rating' => 5,
                'image' => 'newassets/2026-2027 DOMESTIC/2026 AMEGA BORACAY  NEW.jpg',
                'description' => 'White Beach relaxation, island hopping tour, helmet diving, and romantic sunset sailing.',
                'inclusions' => $defaultInclusions,
                'exclusions' => $defaultExclusions,
                'itinerary' => "Day 1: Arrival at Caticlan Airport & Speedboat Transfer to Boracay Beach Resort\nDay 2: Full Day Island Hopping, Crystal Cove & Puka Beach Snorkeling\nDay 3: Paraw Sunset Sailing & Free Time White Beach Nightlife\nDay 4: Breakfast & Transfer to Caticlan Airport",
                'available_dates' => 'Daily Departures Available Year-Round',
                'category' => 'domestic',
                'status' => 'active',
                'is_featured' => true,
            ],
            [
                'destination_id' => $palawan?->id,
                'title' => 'El Nido & Coron Paradise Expedition',
                'duration' => '5 Days / 4 Nights',
                'price' => '₱18,500',
                'rating' => 5,
                'image' => 'newassets/2026-2027 DOMESTIC/2026 AMEGA EL NIDO & CORON.jpg',
                'description' => 'Big Lagoon kayaking, Secret Beach exploration, Kayangan Lake & limestone cliffs.',
                'inclusions' => $defaultInclusions,
                'exclusions' => $defaultExclusions,
                'itinerary' => "Day 1: Arrival in Puerto Princesa & Transfer to El Nido Beachfront Hotel\nDay 2: El Nido Tour A (Big Lagoon, Secret Lagoon, Shimizu Island & Seven Commandos)\nDay 3: El Nido Tour C (Hidden Beach, Matinloc Shrine & Secret Beach)\nDay 4: Transfer to Coron & Kayangan Lake Snorkeling\nDay 5: Souvenir Shopping & Departure Flight",
                'available_dates' => 'Every Monday & Friday',
                'category' => 'domestic',
                'status' => 'active',
                'is_featured' => true,
            ],
            [
                'destination_id' => $tokyo?->id,
                'title' => 'Japan Hokkaido Snow Festival',
                'duration' => '6 Days / 5 Nights',
                'price' => '$2,399',
                'rating' => 5,
                'image' => 'newassets/2026-2027 SHORT HAUL/JPG/2026 AMEGA JAPAN HOKKAIDO SNOW FESTIVAL NEW.jpg',
                'description' => 'Sapporo Snow Festival, hot springs, Otaru canal, and winter wonderland experience.',
                'inclusions' => $defaultInclusions,
                'exclusions' => $defaultExclusions,
                'itinerary' => "Day 1: Arrival in Sapporo Chitose Airport & Hotel Transfer\nDay 2: Sapporo Snow Festival & Odori Park Sculptures Tour\nDay 3: Otaru Canal Romantic Stroll & Music Box Museum\nDay 4: Jozankei Onsen Hot Springs & Snow Activity Park\nDay 5: Shiroi Koibito Park & Tanukikoji Shopping District\nDay 6: Souvenir Shopping & Departure Flight Back Home",
                'available_dates' => 'Dec 15 - Dec 20, Jan 10 - Jan 15, Feb 5 - Feb 10',
                'category' => 'short_haul',
                'status' => 'active',
                'is_featured' => true,
            ],
            [
                'destination_id' => $seoul?->id,
                'title' => 'South Korea Autumn Special',
                'duration' => '6 Days / 5 Nights',
                'price' => '$1,899',
                'rating' => 5,
                'image' => 'newassets/2026-2027 SHORT HAUL/JPG/2026 AMEGA SOKOR AUTUMN NEW NEW.jpg',
                'description' => 'Fall foliage in Nami Island, Gyeongbokgung Palace, Seoul shopping & K-culture.',
                'inclusions' => $defaultInclusions,
                'exclusions' => $defaultExclusions,
                'itinerary' => "Day 1: Arrival at Incheon International Airport & Transfer to Seoul Hotel\nDay 2: Nami Island Autumn Foliage Walk & Petite France Village\nDay 3: Gyeongbokgung Palace Hanbok Experience & Bukchon Hanok Village\nDay 4: N Seoul Tower & Myeongdong Shopping District\nDay 5: Lotte World Theme Park & Han River Sunset Cruise\nDay 6: Local Market Shopping & Departure Flight",
                'available_dates' => 'Oct 12 - Oct 17, Oct 24 - Oct 29, Nov 05 - Nov 10',
                'category' => 'short_haul',
                'status' => 'active',
                'is_featured' => true,
            ],
            [
                'destination_id' => $dubai?->id,
                'title' => 'Dubai & Abu Dhabi All-In',
                'duration' => '6 Days / 5 Nights',
                'price' => '$2,199',
                'rating' => 5,
                'image' => 'newassets/2026-2027 SHORT HAUL/JPG/2026 AMEGA DUBAI ABU DHABI.jpg',
                'description' => 'Burj Khalifa, Desert Safari, Grand Mosque Abu Dhabi & luxury shopping.',
                'inclusions' => $defaultInclusions,
                'exclusions' => $defaultExclusions,
                'itinerary' => "Day 1: Arrival at Dubai International Airport & Hotel Check-in\nDay 2: Modern Dubai City Tour & Burj Khalifa 124th Floor Deck\nDay 3: Afternoon Desert Safari with 4x4 Dune Bashing & BBQ Dinner\nDay 4: Full Day Abu Dhabi Tour & Sheikh Zayed Grand Mosque\nDay 5: Dubai Mall, Gold Souk & Marina Dhow Cruise Dinner\nDay 6: Free Time Shopping & Departure",
                'available_dates' => 'Nov 15 - Nov 20, Dec 01 - Dec 06, Jan 15 - Jan 20',
                'category' => 'short_haul',
                'status' => 'active',
                'is_featured' => true,
            ],
            [
                'destination_id' => null,
                'title' => 'Spain & Portugal Grand Tour',
                'duration' => '12 Days / 11 Nights',
                'price' => '$4,299',
                'rating' => 5,
                'image' => 'newassets/2026-2027 LONG HAUL/JPG/2026 - 2027 AMEGA SPAIN & PORTUGAL.jpg',
                'description' => 'Madrid, Barcelona Sagrada Familia, Lisbon coast, flamenco shows & tapas.',
                'inclusions' => $defaultInclusions,
                'exclusions' => $defaultExclusions,
                'itinerary' => "Day 1-3: Madrid Royal Palace & Prado Museum\nDay 4-6: Seville Flamenco Show & Granada Alhambra Palace\nDay 7-9: Lisbon Belem Tower & Sintra Palace\nDay 10-12: Barcelona Sagrada Familia & Park Guell Tour",
                'available_dates' => 'Sep 20 - Oct 01, Oct 15 - Oct 26, Nov 02 - Nov 13',
                'category' => 'long_haul',
                'status' => 'active',
                'is_featured' => true,
            ],
            [
                'destination_id' => $bali?->id,
                'title' => 'Bali Island Wellness',
                'duration' => '6 Days / 5 Nights',
                'price' => '$1,299',
                'rating' => 5,
                'image' => 'newassets/2026-2027 SHORT HAUL/JPG/2026-2027 AMEGA BALI NEW.jpg',
                'description' => 'Ubud rice terraces, luxury beach resort, spa sessions & sunset temples.',
                'inclusions' => $defaultInclusions,
                'exclusions' => $defaultExclusions,
                'itinerary' => "Day 1: Arrival at Denpasar Airport & Resort Transfer\nDay 2: Ubud Tegalalang Rice Terraces & Sacred Monkey Forest\nDay 3: Tirta Empul Holy Water Temple & Spa Wellness Session\nDay 4: Tanah Lot Temple Sunset & Jimbaran Seafood Dinner\nDay 5: Nusa Penida Island Day Tour & Kelingking Cliff View\nDay 6: Beach Relaxation & Departure Flight",
                'available_dates' => 'Oct 05 - Oct 10, Nov 10 - Nov 15, Dec 05 - Dec 10',
                'category' => 'short_haul',
                'status' => 'active',
                'is_featured' => true,
            ],
            [
                'destination_id' => null,
                'title' => 'Australia Express Wonders',
                'duration' => '8 Days / 7 Nights',
                'price' => '$3,599',
                'rating' => 5,
                'image' => 'newassets/2026-2027 LONG HAUL/JPG/2026 AMEGA AUSTRALIA NEW B.jpg',
                'description' => 'Sydney Opera House, Bondi Beach, Blue Mountains & wildlife sanctuary.',
                'inclusions' => $defaultInclusions,
                'exclusions' => $defaultExclusions,
                'itinerary' => "Day 1: Arrival in Sydney & Harbour Bridge Walk\nDay 2: Sydney Opera House Guided Tour & Bondi Beach\nDay 3: Blue Mountains Day Trip & Scenic World Cableway\nDay 4: Koala & Kangaroo Wildlife Sanctuary Tour\nDay 5-7: Melbourne Great Ocean Road & Twelve Apostles Tour\nDay 8: Departure Back to Manila",
                'available_dates' => 'Oct 20 - Oct 27, Nov 12 - Nov 19, Dec 01 - Dec 08',
                'category' => 'long_haul',
                'status' => 'active',
                'is_featured' => true,
            ],
        ];

        foreach ($packages as $pkg) {
            TravelPackage::updateOrCreate(
                ['title' => $pkg['title']],
                $pkg
            );
        }
    }
}
