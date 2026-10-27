<?php

use App\Support\WebImage;
use Illuminate\Support\Facades\File;

/**
 * The public website shows small WebP copies of its print-size photos, made once and cached under
 * storage/app/public/thumbs.
 */
afterEach(fn () => File::deleteDirectory(storage_path('app/public/thumbs/37')));

test('a large photo gets a smaller webp copy at the width asked for', function () {
    $path = 'storage/web-image-test.jpg';
    $source = storage_path('app/public/web-image-test.jpg');
    $image = imagecreatetruecolor(1200, 900);
    for ($i = 0; $i < 3000; $i++) {
        imagesetpixel($image, random_int(0, 1199), random_int(0, 899), random_int(0, 0xFFFFFF));
    }
    imagejpeg($image, $source, 95);

    $url = WebImage::url($path, 37);

    expect($url)->toContain('/storage/thumbs/37/')->toEndWith('.webp');
    $copy = storage_path('app/public/'.Str::after($url, '/storage/'));
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
