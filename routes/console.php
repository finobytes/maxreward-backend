<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\OrderAutoCompleteService;
use Illuminate\Support\Facades\Log;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks - Auto-Complete Orders
|--------------------------------------------------------------------------
|
| Instead of using a console command, we directly use the service
| This is more efficient and avoids command-related issues
|
*/

// Run auto-complete every hour
Schedule::call(function () {
    try {
        Log::info('=== Scheduled Auto-Complete Started ===');
        
        $service = app(OrderAutoCompleteService::class);
        $results = $service->processAutoCompletion();
        
        Log::info('=== Scheduled Auto-Complete Completed ===', [
            'total_orders' => $results['total'],
            'success' => $results['success'],
            'failed' => $results['failed']
        ]);
        
        // Optional: Send notification to admin if there are failures
        if ($results['failed'] > 0) {
            Log::warning('Some orders failed auto-completion', [
                'failed_count' => $results['failed'],
                'errors' => $results['errors']
            ]);
            
            // You can add email notification here if needed
            // Mail::to('admin@example.com')->send(new AutoCompleteFailureNotification($results));
        }
        
    } catch (\Exception $e) {
        Log::error('Scheduled auto-complete failed: ' . $e->getMessage(), [
            'exception' => $e->getTraceAsString()
        ]);
    }
})->hourly()->name('auto-complete-orders')->withoutOverlapping();
