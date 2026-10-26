<?php

use App\Models\GalleryItem;

test('the traveller photos hang on the polaroid line, each loaded only when needed', function () {
    GalleryItem::create(['title' => 'Beach paradise', 'category' => 'islands', 'image' => 'images/gallery/beach-1.jpg', 'order' => 1]);
    GalleryItem::create(['title' => 'Pinatubo trek', 'category' => 'adventure', 'image' => 'images/gallery/pinatubo-1.jpg', 'order' => 2]);

    $html = $this->get(route('home'))->assertOk()->getContent();

    expect($html)->toContain('data-polaroid-carousel')
        ->toContain('aria-roledescription="carousel"')
        ->toContain('data-title="Beach paradise"')
        ->toContain('data-caption="Islands"')
        // The photo waits in data-src until its print nears the middle.
        ->toContain('data-src="'.asset('images/gallery/beach-1.jpg').'"')
        ->not->toContain('<img src="'.asset('images/gallery/beach-1.jpg').'"')
        ->toContain('<b data-pl-index>01</b> / 02');
});

test('with no photos the section shows no empty carousel', function () {
    expect($this->get(route('home'))->assertOk()->getContent())->not->toContain('data-polaroid-carousel');
});
