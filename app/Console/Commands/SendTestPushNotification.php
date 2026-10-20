<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\SystemPushNotification;
use Illuminate\Console\Command;

class SendTestPushNotification extends Command
{
    protected $signature = 'push:test {user? : User ID or email}';
    protected $description = 'Dispatch a test Dual-Path push notification to verify in-app, bell, and OS desktop alerts';

    public function handle(): int
    {
        $identifier = $this->argument('user') ?? 6;

        $user = is_numeric($identifier)
            ? User::find((int) $identifier)
            : User::where('email', $identifier)->first();

        if (!$user) {
            $this->error("User not found for identifier: {$identifier}");
            return Command::FAILURE;
        }

        $subCount = $user->pushSubscriptions()->count();
        $this->info("Found User: #{$user->id} {$user->name} ({$user->email}) with {$subCount} push subscription(s).");

        if ($subCount === 0) {
            $this->warn("Warning: This user has 0 active browser push subscriptions in push_subscriptions table.");
        }

        $title = "System Notification #" . rand(100, 999);
        $body = "Real-time dual-path alert delivered at " . now()->format('H:i:s') . ".";

        $this->info("Dispatching SystemPushNotification...");
        $user->notify(new SystemPushNotification(
            title: $title,
            body: $body,
            actionUrl: '/vouchers',
            tagId: 'test-' . time()
        ));

        $this->info("✅ Notification dispatched! Check your screen (both in-app toast & Windows desktop banner).");
        return Command::SUCCESS;
    }
}
