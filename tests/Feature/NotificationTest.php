<?php

namespace SameOldNick\NotificationPreferences\Tests\Feature;

use Illuminate\Support\Facades\Notification;
use SameOldNick\NotificationPreferences\Tests\Fixtures\TestNotification;
use SameOldNick\NotificationPreferences\Tests\TestCase;

class NotificationTest extends TestCase
{
    public function test_routes_notification_via_notifiable_preferences(): void
    {
        Notification::fake();

        $notifiable = $this->createNotifiable();
        $this->createPreference($notifiable, 'login', 'mail', true);
        $this->createPreference($notifiable, 'login', 'ntfy', false);

        $notification = new TestNotification(
            data: ['message' => 'Test message'],
            notificationType: 'login',
        );

        $notifiable->notify($notification);

        Notification::assertSentTo(
            $notifiable,
            function (TestNotification $notification, array $channels) {
                dd($channels);

                return $channels === ['mail'];
            },
        );
    }
}
