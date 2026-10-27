<?php

namespace App\Console\Commands;

use App\Models\Destination;
use App\Models\GalleryItem;
use App\Models\Service;
use App\Models\TravelPackage;
use App\Support\WebImage;
use Illuminate\Console\Command;

/**
 * Make the public website's small photo copies ahead of time (see WebImage), so no visitor waits
 * while they are made. Safe to run again: existing copies are kept. Run it after a deploy that
 * adds photos; pages also make missing ones a few at a time.
 */
class OptimizeImages extends Command
{
    protected $signature = 'images:optimize';

    protected $description = 'Make the small web copies of the public website photos';

    public function handle(): int
    {
        $jobs = collect();

        // Database photos: shown in cards and opened full screen.
        foreach ([TravelPackage::class, Destination::class, GalleryItem::class, Service::class] as $model) {
            $model::query()->whereNotNull('image')->pluck('image')
                ->each(fn (string $path) => $jobs->push([$path, WebImage::CARD], [$path, WebImage::WIDE]));
        }

        // The hero's moving background.
        foreach (glob(public_path('images/public moving background').'/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [] as $file) {
            $jobs->push(['images/public moving background/'.basename($file), WebImage::MARQUEE]);
        }

        // Logos, the call-to-action background and the gallery reel posters.
        foreach (['Bureau_of_Immigration_PH_2022.png', 'PhilipineretirementAutorityLogo.jpg', 'clarkdevelopmentlogo.webp', 'attap.png', 'ptaa.png', 'harp.jpg'] as $logo) {
            $jobs->push(['images/logoapproved/'.$logo, WebImage::LOGO]);
        }
        $jobs->push(['images/cta/cta-bg.jpg', WebImage::WIDE], ['images/services/visa.jpg', WebImage::CARD]);
        foreach (glob(public_path('newassets/Amega Reel-Tiktok videos').'/*/*.jpg') ?: [] as $file) {
            $jobs->push([str_replace('\\', '/', substr($file, strlen(public_path()) + 1)), WebImage::MARQUEE]);
        }

        $jobs = $jobs->unique(fn (array $job) => $job[0].'@'.$job[1]);
        $this->withProgressBar($jobs, fn (array $job) => WebImage::url($job[0], $job[1]));
        $this->newLine();
        $this->info($jobs->count().' photo copies ready.');

        return self::SUCCESS;
    }
}
