<?php

namespace SameOldNick\NotificationPreferences\Tests\Unit;

use InvalidArgumentException;
use SameOldNick\NotificationPreferences\Contracts\NotificationCatalog;
use SameOldNick\NotificationPreferences\Tests\TestCase;
use stdClass;

class NotificationCatalogConfigProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'notification-preferences.types' => [
                'login' => ['label' => 'Login', 'category' => 'Security'],
                'backup_failed' => ['label' => 'Backup Failed', 'category' => 'Backups'],
            ],
            'notification-preferences.available_channels' => ['mail', 'ntfy'],
            'notification-preferences.required_channels' => ['app'],
        ]);
    }

    public function test_available_channels_uses_the_global_channels_when_the_type_value_is_null(): void
    {
        config(['notification-preferences.types.login.available_channels' => null]);

        $this->assertSame(
            ['mail', 'ntfy'],
            $this->catalog()->availableNotificationChannelsFor('login', new stdClass),
        );
    }

    public function test_available_channels_uses_the_global_channels_when_the_type_value_is_missing(): void
    {
        $this->assertSame(
            ['mail', 'ntfy'],
            $this->catalog()->availableNotificationChannelsFor('login', new stdClass),
        );
    }

    public function test_available_channels_uses_the_type_specific_channels(): void
    {
        config(['notification-preferences.types.login.available_channels' => ['mail']]);

        $this->assertSame(
            ['mail'],
            $this->catalog()->availableNotificationChannelsFor('login', new stdClass),
        );
    }

    public function test_available_channels_does_not_use_the_global_channels_when_the_type_value_is_empty(): void
    {
        config(['notification-preferences.types.login.available_channels' => []]);

        $this->assertSame(
            [],
            $this->catalog()->availableNotificationChannelsFor('login', new stdClass),
        );
    }

    public function test_available_channels_is_scoped_to_the_requested_type(): void
    {
        config(['notification-preferences.types.login.available_channels' => ['mail']]);

        $this->assertSame(
            ['mail', 'ntfy'],
            $this->catalog()->availableNotificationChannelsFor('backup_failed', new stdClass),
        );
    }

    public function test_available_channels_reindexes_the_type_specific_channels(): void
    {
        config(['notification-preferences.types.login.available_channels' => ['primary' => 'mail', 'secondary' => 'ntfy']]);

        $this->assertSame(
            ['mail', 'ntfy'],
            $this->catalog()->availableNotificationChannelsFor('login', new stdClass),
        );
    }

    public function test_available_channels_throws_when_the_type_value_is_not_an_array_or_null(): void
    {
        config(['notification-preferences.types.login.available_channels' => 'mail']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The [notification-preferences.types.login.available_channels] configuration value must be an array or null, string given.');

        $this->catalog()->availableNotificationChannelsFor('login', new stdClass);
    }

    public function test_required_channels_uses_the_global_channels_when_the_type_value_is_null(): void
    {
        config(['notification-preferences.types.login.required_channels' => null]);

        $this->assertSame(
            ['app'],
            $this->catalog()->requiredNotificationChannelsFor('login', new stdClass),
        );
    }

    public function test_required_channels_uses_the_global_channels_when_the_type_value_is_missing(): void
    {
        $this->assertSame(
            ['app'],
            $this->catalog()->requiredNotificationChannelsFor('login', new stdClass),
        );
    }

    public function test_required_channels_uses_the_type_specific_channels(): void
    {
        config(['notification-preferences.types.login.required_channels' => ['mail']]);

        $this->assertSame(
            ['mail'],
            $this->catalog()->requiredNotificationChannelsFor('login', new stdClass),
        );
    }

    public function test_required_channels_does_not_use_the_global_channels_when_the_type_value_is_empty(): void
    {
        config(['notification-preferences.types.login.required_channels' => []]);

        $this->assertSame(
            [],
            $this->catalog()->requiredNotificationChannelsFor('login', new stdClass),
        );
    }

    public function test_required_channels_is_scoped_to_the_requested_type(): void
    {
        config(['notification-preferences.types.login.required_channels' => ['mail']]);

        $this->assertSame(
            ['app'],
            $this->catalog()->requiredNotificationChannelsFor('backup_failed', new stdClass),
        );
    }

    public function test_required_channels_throws_when_the_type_value_is_not_an_array_or_null(): void
    {
        config(['notification-preferences.types.login.required_channels' => 'app']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The [notification-preferences.types.login.required_channels] configuration value must be an array or null, string given.');

        $this->catalog()->requiredNotificationChannelsFor('login', new stdClass);
    }

    private function catalog(): NotificationCatalog
    {
        return $this->app->make(NotificationCatalog::class);
    }
}
