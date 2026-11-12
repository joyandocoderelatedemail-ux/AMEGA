<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Morning reminder to each agent whose ticketing quotation ends tomorrow, ends today or has ended.
Schedule::command('ticketing:send-quotation-reminders')
    ->dailyAt('08:00')
    ->timezone('Asia/Manila')
    ->withoutOverlapping();

// Every hour: the agent whose airline hold must be ticketed within 24 hours (once per deadline).
Schedule::command('ticketing:send-deadline-reminders')
    ->hourly()
    ->withoutOverlapping();

// Morning reminder to immigration clients whose visa expires within 7 days.
Schedule::command('immigration:send-expiry-reminders')
    ->dailyAt('08:00')
    ->timezone('Asia/Manila')
    ->withoutOverlapping();
