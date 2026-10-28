<?php

use App\Support\WebImage;
use Illuminate\Support\Facades\File;

/**
 * The public website shows small WebP copies of its print-size photos, made once and cached under
 * thumbs/ of the web root.
 */
afterEach(fn () => File::deleteDirectory(WebImage::webRoot().'/thumbs/37'));

test('a large photo gets a smaller webp copy at the width asked for', function () {
    $path = 'storage/web-image-test.jpg';
    $source = storage_path('app/public/web-image-test.jpg');
    $image = imagecreatetruecolor(1200, 900);
    for ($i = 0; $i < 3000; $i++) {
        imagesetpixel($image, random_int(0, 1199), random_int(0, 899), random_int(0, 0xFFFFFF));
    }
    imagejpeg($image, $source, 95);

    $url = WebImage::url($path, 37);

    // Served straight from the web root, not through the /storage link (the host will not follow it).
    expect($url)->toStartWith(asset('thumbs/37/'))->toEndWith('.webp');
    $copy = WebImage::webRoot().'/thumbs/'.Str::after($url, '/thumbs/');
    expect(getimagesize($copy)[0])->toBe(37)
        ->and(filesize($copy))->toBeLessThan(filesize($source));

    // Made once: asking again links the same file.
    expect(WebImage::url($path, 37))->toBe($url);

    unlink($source);
});

test('anything it cannot copy keeps its original address', function () {
    expect(WebImage::url('https://example.com/a.jpg'))->toBe('https://example.com/a.jpg')
        ->and(WebImage::url('images/does-not-exist.jpg'))->toBe(asset('images/does-not-exist.jpg'))
        ->and(WebImage::url('../.env'))->toBe(asset('../.env'))
        ->and(WebImage::url(null))->toBe('');
});

test('the hero background draws a share of the photos per row, not the whole deck five times', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();
    $hero = Str::between($html, 'id="hero"', 'id="services"');

    expect(substr_count($hero, '<img'))->toBeLessThanOrEqual(5 * 2 * 13);
});
