<?php

namespace Tests;

use App\Support\SpamTrap;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Posts carry the public forms' spam-trap timer and shield, as the real pages send them (App\Support\SpamTrap). */
    public function post($uri, array $data = [], array $headers = [])
    {
        return parent::post($uri, $data + $this->spamTrapDefaults(), $headers);
    }

    public function postJson($uri, array $data = [], array $headers = [], $options = 0)
    {
        return parent::postJson($uri, $data + $this->spamTrapDefaults(), $headers, $options);
    }

    /** @return array{form_started: string, _shield: string} */
    private function spamTrapDefaults(): array
    {
        if (! session()->isStarted()) {
            session()->start();
        }

        return [
            'form_started' => encrypt(now()->subMinute()->timestamp),
            '_shield' => SpamTrap::expectedShield((string) session()->token()),
        ];
    }
}
