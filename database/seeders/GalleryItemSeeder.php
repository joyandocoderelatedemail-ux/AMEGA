<?php

namespace Database\Seeders;

use App\Models\GalleryItem;
use Illuminate\Database\Seeder;

class GalleryItemSeeder extends Seeder
{
    public function run(): void
    {
        $gallery = [
            ['title' => 'Beach paradise', 'category' => 'islands', 'image' => 'images/gallery/beach-1.jpg', 'order' => 1],
            ['title' => 'Coastal sunset', 'category' => 'islands', 'image' => 'images/gallery/beach-2.jpg', 'order' => 2],
            ['title' => 'Tropical escape', 'category' => 'islands', 'image' => 'images/gallery/beach-3.jpg', 'order' => 3],
            ['title' => 'Mt. Pinatubo crater', 'category' => 'adventure', 'image' => 'images/gallery/pinatubo-1.jpg', 'order' => 4],
            ['title' => 'Volcanic landscape', 'category' => 'adventure', 'image' => 'images/gallery/pinatubo-2.jpg', 'order' => 5],
            ['title' => 'Crater lake view', 'category' => 'adventure', 'image' => 'images/gallery/pinatubo-3.jpg', 'order' => 6],
            ['title' => 'Mountain trek', 'category' => 'adventure', 'image' => 'images/gallery/pinatubo-4.jpg', 'order' => 7],
            ['title' => 'Puning Hot Spring', 'category' => 'wellness', 'image' => 'images/gallery/hotspring-1.jpg', 'order' => 8],
            ['title' => 'Natural springs', 'category' => 'wellness', 'image' => 'images/gallery/hotspring-2.jpg', 'order' => 9],
            ['title' => 'Hot spring resort', 'category' => 'wellness', 'image' => 'images/gallery/hotspring-3.jpg', 'order' => 10],
            ['title' => 'Clark tour', 'category' => 'city', 'image' => 'images/gallery/clark-1.jpg', 'order' => 11],
            ['title' => 'City exploration', 'category' => 'city', 'image' => 'images/gallery/clark-2.jpg', 'order' => 12],
            ['title' => 'AMEGA event', 'category' => 'events', 'image' => 'images/gallery/event-1.jpg', 'order' => 13],
            ['title' => 'Celebration', 'category' => 'events', 'image' => 'images/gallery/event-2.jpg', 'order' => 14],
            ['title' => 'Mountain adventure', 'category' => 'adventure', 'image' => 'images/gallery/mt-1.jpg', 'order' => 15],
            ['title' => 'Scenic trail', 'category' => 'adventure', 'image' => 'images/gallery/mt-2.jpg', 'order' => 16],
        ];

        foreach ($gallery as $g) {
            GalleryItem::updateOrCreate(
                ['title' => $g['title'], 'image' => $g['image']],
                $g
            );
        }
    }
}
