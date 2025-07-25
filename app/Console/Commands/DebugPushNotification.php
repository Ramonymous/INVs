<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\InvRequest;
use App\Models\User;
use App\Notifications\RequestPushed;
use Illuminate\Support\Facades\Log;

class DebugPushNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'debug:push-notification {request_id?} {user_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Debug push notifications for a specific request and user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Starting push notification debug...');

        // Get parameters
        $requestId = $this->argument('request_id');
        $userId = $this->argument('user_id');

        // If no request ID provided, get the latest request
        if (!$requestId) {
            $latestRequest = InvRequest::latest()->first();
            if (!$latestRequest) {
                $this->error('No requests found in database');
                return 1;
            }
            $requestId = $latestRequest->id;
            $this->info("Using latest request ID: {$requestId}");
        }

        // If no user ID provided, get the request owner or first user with push subscriptions
        if (!$userId) {
            $request = InvRequest::find($requestId);
            if ($request && $request->user_id) {
                $userId = $request->requested_by;
                $this->info("Using request owner user ID: {$userId}");
            } else {
                $userWithPush = User::whereHas('pushSubscriptions')->first();
                if ($userWithPush) {
                    $userId = $userWithPush->id;
                    $this->info("Using first user with push subscriptions: {$userId}");
                } else {
                    $this->error('No users with push subscriptions found');
                    return 1;
                }
            }
        }

        $this->info("Testing with Request ID: {$requestId}, User ID: {$userId}");
        $this->newLine();

        // 1. Check if request exists
        $this->info('1. Checking if request exists...');
        $request = InvRequest::with('items.part', 'user')->find($requestId);
        if (!$request) {
            $this->error("❌ Request with ID {$requestId} not found");
            return 1;
        }
        $this->info("✅ Request found: #{$request->id}");
        $this->table(['Field', 'Value'], [
            ['ID', $request->id],
            ['Destination', $request->destination ?? 'null'],
            ['User ID', $request->user_id ?? 'null'],
            ['Created At', $request->created_at],
            ['Items Count', $request->items->count()]
        ]);

        // 2. Check if user exists and has push subscriptions
        $this->newLine();
        $this->info('2. Checking user and push subscriptions...');
        $user = User::find($userId);
        if (!$user) {
            $this->error("❌ User with ID {$userId} not found");
            return 1;
        }
        $this->info("✅ User found: {$user->name} ({$user->email})");

        $pushSubscriptionsCount = $user->pushSubscriptions()->count();
        if ($pushSubscriptionsCount === 0) {
            $this->warn("⚠️ User has no push subscriptions");
        } else {
            $this->info("✅ User has {$pushSubscriptionsCount} push subscription(s)");
        }

        // 3. Test notification creation
        $this->newLine();
        $this->info('3. Testing notification creation...');
        try {
            $notification = new RequestPushed($requestId);
            $this->info('✅ Notification created successfully');
            
            // Test the toWebPush method
            $webPushMessage = $notification->toWebPush($user, $notification);
            if ($webPushMessage === null) {
                $this->warn('⚠️ toWebPush() returned null - check logs for details');
            } else {
                $this->info('✅ WebPushMessage created successfully');
                $this->info('Message details:');
                // Note: We can't easily inspect the private properties of WebPushMessage
                // but we can confirm it was created
            }
        } catch (\Exception $e) {
            $this->error("❌ Error creating notification: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        }

        // 4. Test actual notification sending
        $this->newLine();
        if ($this->confirm('4. Do you want to send an actual push notification to this user?')) {
            try {
                Log::info('Manual push notification test started', [
                    'request_id' => $requestId,
                    'user_id' => $userId,
                    'command' => 'debug:push-notification'
                ]);

                $user->notify(new RequestPushed($requestId));
                $this->info('✅ Push notification queued successfully');
                $this->info('Check your browser/device for the notification');
                $this->info('Also check the Laravel logs for detailed information');
            } catch (\Exception $e) {
                $this->error("❌ Error sending notification: " . $e->getMessage());
                $this->error("Stack trace: " . $e->getTraceAsString());
                return 1;
            }
        }

        // 5. Show log watching command
        $this->newLine();
        $this->info('💡 To watch logs in real-time, run:');
        $this->line('tail -f storage/logs/laravel.log | grep "RequestPushed"');

        $this->newLine();
        $this->info('🎉 Debug completed successfully!');
        
        return 0;
    }
}