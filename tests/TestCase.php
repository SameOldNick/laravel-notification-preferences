<?php

namespace SameOldNick\NotificationPreferences\Tests;

use Illuminate\Config\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\Concerns\WithLaravelMigrations;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;
use SameOldNick\NotificationPreferences\Models\NotifiableNotificationPreference;
use Workbench\App\Models\User;

class TestCase extends Orchestra
{
    use RefreshDatabase;
    use WithLaravelMigrations;
    use WithWorkbench;

    /**
     * Automatically enables package discoveries.
     *
     * @var bool
     */
    protected $enablesPackageDiscoveries = true;

    public function getEnvironmentSetUp($app)
    {
        tap($app['config'], function (Repository $config) {
            $config->set('database.default', 'testing');
        });
    }

    /**
     * Helper method to create a notifiable for testing.
     */
    protected function createNotifiable(): User
    {
        $admin = User::factory()->create();

        return $admin;
    }

    /**
     * Helper method to store a notification preference for a notifiable.
     */
    protected function createPreference(
        User $notifiable,
        string $notificationType,
        string $channel,
        bool $enabled = true,
    ): NotifiableNotificationPreference {
        return $notifiable->notificationPreferences()->create([
            'notification_type' => $notificationType,
            'channel' => $channel,
            'enabled' => $enabled,
        ]);
    }
}
