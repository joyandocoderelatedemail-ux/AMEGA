<?php

use App\Models\Testimonial;

function publishedReview(string $name, string $date, ?int $rating = 5): Testimonial
{
    return Testimonial::create([
        'name' => $name,
        'comment' => "Review from {$name}.",
        'rating' => $rating,
        'reviewed_on' => $date,
        'is_published' => true,
    ]);
}

test('the reviews carousel shows the newest reviews first, with yellow stars', function () {
    foreach (range(1, 7) as $day) {
        publishedReview("Client {$day}", "2026-11-0{$day}", $day === 1 ? 3 : 5);
    }

    $this->get(route('home'))->assertOk()
        ->assertSee('id="testimonial-track"', false)
        ->assertSeeInOrder(['Client 7', 'Client 6', 'Client 5', 'Client 4', 'Client 3', 'Client 2', 'Client 1'])
        ->assertSee('Rated 3 out of 5')
        ->assertSee('text-yellow-400', false)
        ->assertDontSee('Based on');
});

test('the reviews section has an empty state when nothing is published', function () {
    $this->get(route('testimonials'))->assertOk()
        ->assertSee('Client reviews will appear here soon.')
        ->assertDontSee('id="testimonial-track"', false);
});

test('a long review shows five lines and a Read more, so it does not stretch every card', function () {
    $long = publishedReview('Arnoldo P.', '2026-11-01');
    $long->update(['comment' => str_repeat('Excellent service from the desk to the airport. ', 12)]);
    publishedReview('Carlos M.', '2026-11-02');

    $html = $this->get(route('home'))->assertOk()->getContent();

    expect($html)->toContain('line-clamp-5')
        ->toContain('aria-controls="review-'.$long->id.'"')
        ->toContain('Read more')
        // The short review is shown whole, with no button.
        ->and(substr_count($html, 'x-text="open ? \'Show less\' : \'Read more\'"'))->toBe(1);
});
