<?php

use Illuminate\Support\Facades\Log;
use App\Models\Booking;
use Illuminate\Console\Scheduling\Schedule;

protected function schedule(Schedule $schedule)
{
    // Run daily at midnight
    $schedule->call(function () {
        $deletedCount = Booking::where('visit_date', '<', now()->toDateString())->delete();

        Log::info("Expired bookings cleanup: {$deletedCount} deleted.");
    })->daily();
}
