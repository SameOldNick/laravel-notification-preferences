<?php

namespace SameOldNick\NotificationPreferences\Tests\Feature;

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Notification;
use SameOldNick\NotificationPreferences\Attributes\NotificationType as NotificationTypeAttribute;
use SameOldNick\NotificationPreferences\Concerns\RoutesViaNotifiablePreferences;
use SameOldNick\NotificationPreferences\Tests\Fixtures\NotificationType;
use SameOldNick\NotificationPreferences\Tests\Fixtures\TestNotification;
use SameOldNick\NotificationPreferences\Tests\TestCase;
use stdClass;
use Workbench\App\Models\User;

class NotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'notification-preferences.types' => [
                'login' => ['label' => 'Login', 'category' => 'Security'],
                'backup_failed' => ['label' => 'Backup Failed', 'category' => 'Backups'],
            ],
            'notification-preferences.channels' => [
                'mail' => ['label' => 'Email', 'enabled_by_default' => true],
                'ntfy' => ['label' => 'Ntfy', 'enabled_by_default' => true],
            ],
            'notification-preferences.available_channels' => ['mail', 'ntfy'],
            'notification-preferences.required_channels' => [],
        ]);
    }

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
                return $channels === ['mail'];
            },
        );
    }

    public function test_notification_type_returns_the_given_type(): void
    {
        $this->assertSame('login', (new TestNotification([], 'login'))->notificationType());
    }

    public function test_notification_type_falls_back_to_the_notification_class_name(): void
    {
        $this->assertSame(TestNotification::class, (new TestNotification([]))->notificationType());
    }

    public function test_notification_type_falls_back_to_the_notification_class_name_when_empty(): void
    {
        $this->assertSame(TestNotification::class, (new TestNotification([], ''))->notificationType());
    }

    public function test_notification_type_uses_the_notification_type_attribute(): void
    {
        $notification = new #[NotificationTypeAttribute('login')] class extends BaseNotification
        {
            use RoutesViaNotifiablePreferences;
        };

        $this->assertSame('login', $notification->notificationType());
    }

    public function test_notification_type_uses_the_backing_value_of_an_enum_attribute(): void
    {
        $notification = new #[NotificationTypeAttribute(NotificationType::BackupFailed)] class extends BaseNotification
        {
            use RoutesViaNotifiablePreferences;
        };

        $this->assertSame('backup_failed', $notification->notificationType());
    }

    public function test_notification_type_method_takes_precedence_over_the_attribute(): void
    {
        $notification = new #[NotificationTypeAttribute('login')] class extends BaseNotification
        {
            use RoutesViaNotifiablePreferences;

            public function notificationType(): string
            {
                return 'backup_failed';
            }
        };

        $this->assertSame('backup_failed', $notification->notificationType());
    }

    public function test_via_uses_the_notification_type_attribute_to_resolve_preferences(): void
    {
        $notifiable = $this->createNotifiable();
        $this->createPreference($notifiable, 'login', 'mail', false);

        $notification = new #[NotificationTypeAttribute('login')] class extends BaseNotification
        {
            use RoutesViaNotifiablePreferences;
        };

        $this->assertSame(['ntfy'], $notification->via($notifiable));
    }

    public function test_via_returns_no_channels_without_a_notifiable(): void
    {
        $this->assertSame([], (new TestNotification([], 'login'))->via());
    }

    public function test_via_returns_no_channels_for_a_null_notifiable(): void
    {
        $this->assertSame([], (new TestNotification([], 'login'))->via(null));
    }

    public function test_via_returns_all_available_channels_when_no_preferences_are_stored(): void
    {
        $notifiable = $this->createNotifiable();

        $this->assertSame(['mail', 'ntfy'], (new TestNotification([], 'login'))->via($notifiable));
    }

    public function test_via_drops_a_channel_that_is_disabled(): void
    {
        $notifiable = $this->createNotifiable();
        $this->createPreference($notifiable, 'login', 'mail', false);

        $this->assertSame(['ntfy'], (new TestNotification([], 'login'))->via($notifiable));
    }

    public function test_via_keeps_a_channel_that_is_enabled(): void
    {
        $notifiable = $this->createNotifiable();
        $this->createPreference($notifiable, 'login', 'mail', true);
        $this->createPreference($notifiable, 'login', 'ntfy', false);

        $this->assertSame(['mail'], (new TestNotification([], 'login'))->via($notifiable));
    }

    public function test_via_returns_no_channels_when_every_channel_is_disabled(): void
    {
        $notifiable = $this->createNotifiable();
        $this->createPreference($notifiable, 'login', 'mail', false);
        $this->createPreference($notifiable, 'login', 'ntfy', false);

        $this->assertSame([], (new TestNotification([], 'login'))->via($notifiable));
    }

    public function test_via_ignores_preferences_for_other_notification_types(): void
    {
        $notifiable = $this->createNotifiable();
        $this->createPreference($notifiable, 'backup_failed', 'mail', false);

        $this->assertSame(['mail', 'ntfy'], (new TestNotification([], 'login'))->via($notifiable));
    }

    public function test_via_ignores_preferences_for_unavailable_channels(): void
    {
        $notifiable = $this->createNotifiable();
        $this->createPreference($notifiable, 'login', 'slack', false);

        $this->assertSame(['mail', 'ntfy'], (new TestNotification([], 'login'))->via($notifiable));
    }

    public function test_via_ignores_preferences_of_other_notifiables(): void
    {
        $notifiable = $this->createNotifiable();
        $other = $this->createNotifiable();
        $this->createPreference($other, 'login', 'mail', false);

        $this->assertSame(['mail', 'ntfy'], (new TestNotification([], 'login'))->via($notifiable));
    }

    public function test_via_uses_the_notification_class_name_when_no_type_is_given(): void
    {
        $notifiable = $this->createNotifiable();
        $this->createPreference($notifiable, TestNotification::class, 'mail', false);

        $this->assertSame(['ntfy'], (new TestNotification([]))->via($notifiable));
    }

    public function test_via_returns_available_channels_for_notifiables_without_preference_support(): void
    {
        $this->assertSame(['mail', 'ntfy'], (new TestNotification([], 'login'))->via(new stdClass));
    }

    public function test_via_returns_available_channels_for_an_anonymous_notifiable(): void
    {
        $this->assertSame(['mail', 'ntfy'], (new TestNotification([], 'login'))->via(new AnonymousNotifiable));
    }

    public function test_via_returns_no_channels_when_no_channels_are_available(): void
    {
        config(['notification-preferences.available_channels' => []]);

        $notifiable = $this->createNotifiable();

        $this->assertSame([], (new TestNotification([], 'login'))->via($notifiable));
    }

    public function test_via_returns_channels_in_configured_order(): void
    {
        config(['notification-preferences.available_channels' => ['ntfy', 'mail']]);

        $notifiable = $this->createNotifiable();

        $this->assertSame(['ntfy', 'mail'], (new TestNotification([], 'login'))->via($notifiable));
    }

    public function test_via_deduplicates_available_channels(): void
    {
        config(['notification-preferences.available_channels' => ['mail', 'mail', 'ntfy']]);

        $notifiable = $this->createNotifiable();

        $this->assertSame(['mail', 'ntfy'], (new TestNotification([], 'login'))->via($notifiable));
    }

    public function test_via_includes_a_required_channel_even_when_it_is_disabled(): void
    {
        config(['notification-preferences.required_channels' => ['mail']]);

        $notifiable = $this->createNotifiable();
        $this->createPreference($notifiable, 'login', 'mail', false);
        $this->createPreference($notifiable, 'login', 'ntfy', false);

        $this->assertSame(['mail'], (new TestNotification([], 'login'))->via($notifiable));
    }

    public function test_via_does_not_duplicate_a_required_channel(): void
    {
        config(['notification-preferences.required_channels' => ['mail']]);

        $notifiable = $this->createNotifiable();

        $this->assertSame(['mail', 'ntfy'], (new TestNotification([], 'login'))->via($notifiable));
    }

    public function test_via_appends_required_channels_that_are_not_available(): void
    {
        config(['notification-preferences.required_channels' => ['database']]);

        $notifiable = $this->createNotifiable();

        $this->assertSame(['mail', 'ntfy', 'database'], (new TestNotification([], 'login'))->via($notifiable));
    }

    public function test_required_channels_returns_configured_channels_for_a_notifiable(): void
    {
        config(['notification-preferences.required_channels' => ['mail', 'database']]);

        $notifiable = $this->createNotifiable();

        $this->assertSame(['mail', 'database'], (new TestNotification([], 'login'))->getRequiredChannels($notifiable));
    }

    public function test_required_channels_is_empty_for_an_anonymous_notifiable(): void
    {
        config(['notification-preferences.required_channels' => ['mail']]);

        $this->assertSame([], (new TestNotification([], 'login'))->getRequiredChannels(new AnonymousNotifiable));
    }

    public function test_to_array_returns_the_array_payload(): void
    {
        $notifiable = $this->createNotifiable();

        $this->assertSame(
            ['message' => 'Test message'],
            (new TestNotification(['message' => 'Test message'], 'login'))->toArray($notifiable),
        );
    }

    public function test_to_array_resolves_a_closure_payload_for_the_notifiable(): void
    {
        $notifiable = $this->createNotifiable();

        $notification = new TestNotification(
            fn (User $recipient): array => ['id' => $recipient->getKey()],
            'login',
        );

        $this->assertSame(['id' => $notifiable->getKey()], $notification->toArray($notifiable));
    }

    public function test_notification_is_sent_on_every_available_channel_by_default(): void
    {
        Notification::fake();

        $notifiable = $this->createNotifiable();

        $notifiable->notify(new TestNotification(['message' => 'Test message'], 'login'));

        Notification::assertSentTo(
            $notifiable,
            TestNotification::class,
            fn (TestNotification $notification, array $channels) => $channels === ['mail', 'ntfy'],
        );
    }

    public function test_notification_is_not_sent_when_every_channel_is_disabled(): void
    {
        Notification::fake();

        $notifiable = $this->createNotifiable();
        $this->createPreference($notifiable, 'login', 'mail', false);
        $this->createPreference($notifiable, 'login', 'ntfy', false);

        $notifiable->notify(new TestNotification(['message' => 'Test message'], 'login'));

        Notification::assertNotSentTo($notifiable, TestNotification::class);
    }

    public function test_notification_is_sent_on_a_required_channel_when_disabled(): void
    {
        Notification::fake();
        config(['notification-preferences.required_channels' => ['mail']]);

        $notifiable = $this->createNotifiable();
        $this->createPreference($notifiable, 'login', 'mail', false);
        $this->createPreference($notifiable, 'login', 'ntfy', false);

        $notifiable->notify(new TestNotification(['message' => 'Test message'], 'login'));

        Notification::assertSentTo(
            $notifiable,
            TestNotification::class,
            fn (TestNotification $notification, array $channels) => $channels === ['mail'],
        );
    }

    public function test_notification_respects_the_preferences_of_each_notifiable(): void
    {
        Notification::fake();

        $enabled = $this->createNotifiable();

        $disabled = $this->createNotifiable();
        $this->createPreference($disabled, 'login', 'mail', false);
        $this->createPreference($disabled, 'login', 'ntfy', false);

        $notification = new TestNotification(['message' => 'Test message'], 'login');

        $enabled->notify($notification);
        $disabled->notify($notification);

        Notification::assertSentTo(
            $enabled,
            TestNotification::class,
            fn (TestNotification $notification, array $channels) => $channels === ['mail', 'ntfy'],
        );
        Notification::assertNotSentTo($disabled, TestNotification::class);
    }
}
