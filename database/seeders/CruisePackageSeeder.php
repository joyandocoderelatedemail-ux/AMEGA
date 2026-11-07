<?php

namespace Database\Seeders;

use App\Models\TravelPackage;
use Illuminate\Database\Seeder;

/**
 * The 2026-2027 AMEGA cruise packages, from the marketing posters.
 * Safe to run again: it only creates the packages that are missing and never changes one that exists.
 *
 * Slots are a placeholder (10 a date): set the real allotment under Admin > Travel Packages.
 */
class CruisePackageSeeder extends Seeder
{
    private const IMG = 'newassets/2026-2027 CRUISE/';

    public function run(): void
    {
        foreach ($this->packages() as $row) {
            $departures = $row['departures'];
            unset($row['departures']);

            // Safe on a live site: a package that already exists (and may have its price, slots or dates
            // edited by an admin) is left exactly as it is.
            if (TravelPackage::where('title', $row['title'])->exists()) {
                continue;
            }

            $package = TravelPackage::create(
                $row + [
                    'category' => 'cruise',
                    'package_type' => 'ready_made',
                    'status' => 'active',
                    'price_currency' => 'USD',
                    'rating' => 5,
                    'is_featured' => false,
                ],
            );

            $package->syncDepartures(array_map(
                fn (array $d) => ['departure_date' => $d[0], 'return_date' => $d[1], 'slots' => 10],
                $departures,
            ));
        }
    }

    /** @return list<array<string, mixed>> */
    private function packages(): array
    {
        $contact = "\nFor inquiries: 0949 999 0063 / 0992 922 5733 / 0961 645 9703, ticketing@amegatravelandtour.com";

        return [
            [
                'title' => 'Manila to Japan (Miyakojima) Cruise - Adora Mediterranea',
                'duration' => '5 Days / 4 Nights',
                'price_amount' => 319,
                'image' => self::IMG.'adora-mediterranea-manila-miyakojima.jpg',
                'airfare_inclusion' => 'not_included',
                'description' => 'Sail from the Port of Manila to Miyakojima, Japan on the Adora Mediterranea (Adora Cruises). From $319 for the 3rd pax in triple sharing. Buy 2, get 50% off for the 3rd pax and the 4th pax free (based on quad sharing).',
                'inclusions' => "Roundtrip Cruise Fare via ADORA Mediterranea 5D4N\nAll-day dining, full board meals\nLive evening shows and daytime entertainment activities\nVisa free",
                'exclusions' => "Travel insurance\nPH travel tax (pay online by own)\nPort charges (collected with the tour package) - USD150/pax\nJapan tourist arrival fee (pay by own on board) - USD20/pax\nCruise gratuities (pay by own on board) - HKD640/pax\nOptional tour",
                'itinerary' => "Cabin rates per pax, 1st and 2nd pax (3rd pax in triple sharing, 4th pax free in quad sharing):\nInterior Cabin: USD788 (3rd pax USD319, 60% off)\nOceanview Cabin: USD888 (3rd pax USD449, 50% off)\nBalcony Cabin: USD1,088 (3rd pax USD549, 50% off)\nBalcony Suite: USD1,388 (3rd pax USD699, 50% off)\nGrand Balcony Suite: USD1,688 (3rd pax USD849, 50% off; twin and triple only)",
                'available_dates' => 'Jan 02-06, 2027',
                'remarks' => 'Standard Interior Cabin. Departure port: Manila.'.$contact,
                'departures' => [['2027-01-02', '2027-01-06']],
            ],
            [
                'title' => 'Fly & Cruise Taiwan, Korea and Japan - Star Navigator',
                'duration' => '9 Days / 8 Nights',
                'price_amount' => 1188,
                'image' => self::IMG.'star-navigator-fly-and-cruise.jpg',
                'airfare_inclusion' => 'included',
                'description' => 'Fly from Manila to Taipei, tour the city, then cruise 5 nights on Star Navigator (Star Cruises) to Korea and Japan and back to Keelung. From $1,188 per pax (quad sharing). Visa-free for Taiwan, Japan and Korea (Jeju only).',
                'inclusions' => "Roundtrip airfare via Cebu Pacific\n5 nights cruise\nAirport-hotel-port transfers\n2 nights hotel pre-cruise accommodation\nPre and post tour\nMeals as specified\nKorea e-Group visa (Busan)",
                'exclusions' => "Check-in baggage\nTravel insurance\nPort charges (collect in Manila)\nGratuities (pay on board)\nPH travel tax\nJapan tourist arrival fee (pay on board)\nFuel charge (pay on board)\nShore excursions (buy on board; Busan excursions, rate upon booking)",
                'itinerary' => "Day 1: Departure from Manila, arrival in Taipei. Taipei city tour: Sun Moon Lake and Wenwu Temple\nDay 2: Taipei city tour: National Taichung Theater, Rainbow Village, DIY pineapple cake, Sisi Youth Village\nDay 3: Proceed to Keelung Cruise Port and embark on the Star Navigator cruise\nDay 4: At sea, enjoy the ship's facilities and amenities\nDay 5: Itinerary 1 and 3: Busan International Cruise Port. Itinerary 2: Jeju Port International Passenger Terminal. Onboard amenities or optional shore excursions\nDay 6: Itinerary 1: Sasebo. Itinerary 2: Kagoshima. Itinerary 3: Nagasaki\nDay 7: Naha Cruise Terminal, Japan\nDay 8: Disembark in Keelung. Pingxi Sky Lantern (3-4 persons per lantern), Shifen Old Street, transfer to airport\nDay 9: Departure from Taipei, arrival in Manila\n\nItinerary 1: Keelung-Busan-Sasebo-Naha-Keelung\nItinerary 2: Keelung-Jeju-Kagoshima-Naha-Keelung\nItinerary 3: Keelung-Busan-Nagasaki-Naha-Keelung\n\nRates per pax. Interior stateroom: twin USD1,388, triple USD1,288, quad USD1,188. Balcony stateroom: twin USD1,688, triple USD1,588, quad USD1,488. Mar 19-27 is +USD100.",
                'available_dates' => 'Mar 19-27, Apr 23-May 01, May 07-15, May 21-29, 2027',
                'remarks' => 'Updated as of Oct. 2026.'.$contact,
                'departures' => [
                    ['2027-03-19', '2027-03-27'],
                    ['2027-04-23', '2027-05-01'],
                    ['2027-05-07', '2027-05-15'],
                    ['2027-05-21', '2027-05-29'],
                ],
            ],
            [
                'title' => 'Fly & Cruise Singapore, Penang and Phuket - Navigator of the Seas',
                'duration' => '7 Days / 6 Nights',
                'price_amount' => 1588,
                'image' => self::IMG.'navigator-of-the-seas-fly-and-cruise.jpg',
                'airfare_inclusion' => 'included',
                'description' => 'Fly to Singapore and cruise 4 nights on Royal Caribbean\'s Navigator of the Seas to Penang and Phuket. From $1,588 per pax, twin sharing.',
                'inclusions' => "Roundtrip airfare via Cebu Pacific\n20KG check-in baggage with 7KG hand carry\n1 night pre-cruise accommodation\n4 nights Navigator of the Seas cruise (based on twin sharing)\n1 night post-cruise accommodation\nAirport-hotel-port transfers\nTravel insurance",
                'exclusions' => "Port charges and fuel charge\nGratuities\nShore excursions\nPH travel tax\nTaxes/fees\nTipping",
                'itinerary' => "Day 1: Departure from Manila, arrival in Singapore\nDay 2: Proceed to Singapore Port and embark on the Navigator of the Seas\nDay 3: Arrival in Penang Port. Onboard amenities or optional shore excursions by own. Depart from Penang Port\nDay 4: Arrival in Phuket Deep Sea Port. Onboard amenities or optional shore excursions by own. Depart from Phuket Deep Sea Port\nDay 5: At sea. Shows, live music and entertainment, onboard restaurants, free time shopping and swimming\nDay 6: Disembark at Singapore Port. Free day or avail an optional tour: Universal Studios Singapore\nDay 7: Singapore half-day city tour, transfer to airport, departure from Singapore, arrival in Manila\n\nTwin sharing per pax: Interior Stateroom USD1,588. Balcony Stateroom USD1,888. 3rd and 4th pax rates vary and are subject to change upon inquiry.",
                'available_dates' => 'Mar 16-22, 2027',
                'remarks' => 'Updated as of May 2026.'.$contact,
                'departures' => [['2027-03-16', '2027-03-22']],
            ],
            [
                'title' => 'Fly & Cruise Hong Kong, Japan and Taiwan - Spectrum of the Seas',
                'duration' => '8 Days / 7 Nights',
                'price_amount' => 1788,
                'image' => self::IMG.'spectrum-of-the-seas-fly-and-cruise.jpg',
                'airfare_inclusion' => 'included',
                'description' => 'Fly to Hong Kong and cruise 5 nights on Royal Caribbean\'s Spectrum of the Seas to Naha and Keelung. From $1,788 per pax, twin sharing.',
                'inclusions' => "Roundtrip airfare via Cebu Pacific\n20KG check-in baggage with 7KG hand carry\n1 night pre-cruise accommodation\n5 nights Spectrum of the Seas cruise (based on twin sharing)\n1 night post-cruise accommodation\nAirport-hotel-port transfers\nTravel insurance",
                'exclusions' => "Port charges and fuel charge\nGratuities\nShore excursions\nPH travel tax\nTaxes/fees\nTipping",
                'itinerary' => "Day 1: Departure from Manila, arrival in Hong Kong. Hong Kong city tour: Top of Victoria Peak, Nan Lian Garden, Chi Lin Nunnery, souvenir shop, Wan Chai Harbour Front\nDay 2: Leisure time in the morning, proceed to Hong Kong Port and embark on the Spectrum of the Seas\nDay 3: At sea. Enjoy Royal Caribbean's facilities and amenities\nDay 4: Arrival in Naha Port. Onboard amenities or optional shore excursions by own. Departure from Naha Port\nDay 5: Arrival in Keelung Port. Onboard amenities or optional shore excursions by own. Departure from Keelung Port\nDay 6: At sea. Shows, live music and entertainment, onboard restaurants, free time shopping and swimming\nDay 7: Disembark at Hong Kong Port. Free day or avail an optional tour: Hong Kong Disneyland\nDay 8: Free time for shopping, transfer to airport, departure from Hong Kong, arrival in Manila\n\nTwin sharing per pax: Interior Stateroom USD1,788. Balcony Stateroom USD2,088. 3rd and 4th pax rates vary and are subject to change upon inquiry.",
                'available_dates' => 'Dec 05-12, 2026',
                'remarks' => 'Updated as of May 2026.'.$contact,
                'departures' => [['2026-12-05', '2026-12-12']],
            ],
            [
                'title' => 'Fly & Cruise Taiwan and Japan - MSC Bellissima',
                'duration' => '10 Days / 9 Nights',
                'price_amount' => 1788,
                'image' => self::IMG.'msc-bellissima-fly-and-cruise.jpg',
                'airfare_inclusion' => 'included',
                'description' => 'Fly to Taipei, then cruise 4 nights on MSC Bellissima from Keelung to Kagoshima and Tokyo. From $1,788 per pax for the 3rd and 4th pax, twin sharing.',
                'inclusions' => "Roundtrip airfare via Cebu Pacific\n7KG hand carry\n2 nights pre-cruise accommodation\n4 nights in MSC Bellissima cruise\n2 nights post-cruise accommodation\nAirport-hotel-port transfers",
                'exclusions' => "Port charges\nGratuities\nPH travel tax\nTipping\nJapan visa\nTravel insurance\nCheck-in baggage",
                'itinerary' => "Day 1: Departure from Manila, arrival in Taipei. Taipei city tour, DIY pineapple cake, Beitou Geothermal Valley and hot spring experience\nDay 2: Free and easy: walk around nearby spots and shop at nearby markets\nDay 3: Proceed to Keelung Port and embark on the MSC Bellissima\nDay 4: At sea. Enjoy the ship's facilities and amenities\nDay 5: Arrival at Kagoshima Port. Onboard amenities. Depart from Kagoshima Port\nDay 6: At sea. Enjoy the ship's facilities and amenities\nDay 7: Disembark at Tokyo International Cruise Terminal. Asakusa Nakamise, Skytree photostop, Shibuya Crossing with Hachiko Statue\nDay 8: Proceed to Fuji Area: Oishi Park, Oshino Hakkai Village, shopping at Gotemba Outlet\nDay 9: Free and easy: walk around nearby spots and shop at nearby markets\nDay 10: Transfer to airport, departure from Tokyo, arrival in Manila\n\nTwin sharing per pax. Interior Cabin: 1st and 2nd pax USD1,988, 3rd and 4th pax USD1,788. Sea Balcony Cabin: 1st and 2nd pax USD2,188, 3rd and 4th pax USD1,988.",
                'available_dates' => 'Mar 21-30, 2027',
                'remarks' => 'Updated as of Oct. 2026.'.$contact,
                'departures' => [['2027-03-21', '2027-03-30']],
            ],
            [
                'title' => 'Fly & Cruise Singapore - Disney Adventure',
                'duration' => '5 Days / 4 Nights',
                'price_amount' => 1288,
                'image' => self::IMG.'disney-adventure-fly-and-cruise.jpg',
                'airfare_inclusion' => 'included',
                'description' => 'Fly to Singapore and sail 3 nights on the Disney Adventure (Disney Cruise Line). From $1,288 per pax. Deluxe Oceanview with Verandah: pax 1 and 2 USD1,588, pax 3 and 4 USD1,288.',
                'inclusions' => "Roundtrip airfare via Cebu Pacific\n20 KG check-in baggage with 7 KG hand carry\n1 night pre-cruise accommodation\n3 nights Disney Adventure cruise (based on twin sharing)\nFull board meals onboard\nPost-cruise city tour in Singapore\nPrivate transfers (pre and post)\nEnglish-speaking guide\nFilipino tour escort",
                'exclusions' => "Port charges\nGratuities\nPH travel tax (by own)",
                'itinerary' => "Day 1: Departure from Manila, arrival in Singapore, transfer to hotel\nDay 2: Proceed to the Marina Bay Cruise Centre and embark on the Disney Adventure (breakfast and dinner)\nDay 3: Disney-exclusive experiences and seven unique themed areas (breakfast, lunch, dinner)\nDay 4: World-class entertainment onboard, immersive story-driven experiences (breakfast, lunch, dinner)\nDay 5: Disembark at the Marina Bay Cruise Centre, Singapore half-day city tour, transfer to airport, departure from Singapore\n\nSailings from Mar 28 onward are +USD100.",
                'available_dates' => 'Feb 14-18, Mar 28-Apr 01, Apr 04-08, Apr 18-22, May 02-06, May 16-20, 2027',
                'remarks' => 'Updated as of Jun. 2026.'.$contact,
                'departures' => [
                    ['2027-02-14', '2027-02-18'],
                    ['2027-03-28', '2027-04-01'],
                    ['2027-04-04', '2027-04-08'],
                    ['2027-04-18', '2027-04-22'],
                    ['2027-05-02', '2027-05-06'],
                    ['2027-05-16', '2027-05-20'],
                ],
            ],
        ];
    }
}
