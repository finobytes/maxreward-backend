<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\OrderOnholdPoint;
use App\Http\Controllers\Api\OrderController;

class AutoCompleteOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:auto-complete';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-complete orders that have passed their release date';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting auto-completion of orders...');

        // Find all orders ready for release
        $readyOrders = OrderOnholdPoint::readyForRelease()
            ->with('order')
            ->get();

        $this->info("Found {$readyOrders->count()} orders ready for completion");

        // $controller = new OrderController();
        $orderController = app(OrderController::class);
        $success = 0;
        $failed = 0;

        foreach ($readyOrders as $onholdPoint) {
            $this->line("Processing order ID: {$onholdPoint->order_id} ({$onholdPoint->order->order_number})");
            
            $result = $orderController->releaseOrderPoints($onholdPoint->order_id);
            
            if ($result['success']) {
                $success++;
                $this->info("✓ Order {$onholdPoint->order->order_number} completed successfully");
            } else {
                $failed++;
                $this->error("✗ Order {$onholdPoint->order->order_number} failed: {$result['message']}");
            }
        }

        $this->info("\n=== Summary ===");
        $this->info("Total orders processed: {$readyOrders->count()}");
        $this->info("Successfully completed: {$success}");
        $this->error("Failed: {$failed}");

        return Command::SUCCESS;
    }
}