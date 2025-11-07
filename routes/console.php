<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule subscription expiration check to run every minute
// This is critical for 30-second free trials
Schedule::command('subscriptions:expire')->everyMinute();

// Schedule daily room accommodation charging at midnight
// This ensures all admitted patients are charged daily for their rooms
Schedule::command('billing:charge-daily-rooms')->dailyAt('00:30');
