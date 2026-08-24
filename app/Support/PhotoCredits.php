<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Attribution for the Wikimedia Commons photographs used in the hero marquee.
 *
 * The images are freely licensed but almost all of them are CC BY / CC BY-SA,
 * which obliges us to name the photographer and the licence. /photo-credits
 * renders that list; this class is the single source both it and the marquee
 * read from, so a photo can never appear on the site without its credit.
 */
class PhotoCredits
{
    private const MANIFEST = 'photo-credits.json';

    private const DIRECTORY = 'images/marquee';

    /**
     * Every credited photo, in deck order.
     *
     * @return array<int, array{slug: string, path: string, place: string, author: string, license: string, licence_url: string, source: string}>
     */
    public static function deck(): array
    {
        return Cache::rememberForever('photo-credits.deck', function () {
            $path = resource_path(self::MANIFEST);

            if (! is_file($path)) {
                return [];
            }

            $entries = json_decode(file_get_contents($path), true) ?: [];
            $deck    = [];

            foreach ($entries as $slug => $entry) {
                $relative = self::DIRECTORY . "/{$slug}.jpg";

                // Never credit — or render — a file that is not actually there.
                if (! is_file(public_path($relative))) {
                    continue;
                }

                $deck[] = [
                    'slug'        => $slug,
                    'path'        => $relative,
                    'place'       => $entry['place'] ?? $slug,
                    'author'      => $entry['author'] ?? 'Unknown',
                    'license'     => $entry['license'] ?? '',
                    'licence_url' => $entry['licurl'] ?? '',
                    'source'      => $entry['source'] ?? '',
                ];
            }

            return $deck;
        });
    }
}
