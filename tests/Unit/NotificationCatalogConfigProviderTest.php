<?php

namespace SameOldNick\NotificationPreferences\Tests\Unit;

use InvalidArgumentException;
use SameOldNick\NotificationPreferences\Contracts\NotificationCatalog;
use SameOldNick\NotificationPreferences\Contracts\NotificationCatalogForNotifiable;
use SameOldNick\NotificationPreferences\Contracts\NotificationTypeGuard;
use SameOldNick\NotificationPreferences\Tests\Fixtures\ConfigDrivenGuard;
use SameOldNick\NotificationPreferences\Tests\Fixtures\NotifiablePropertyGuard;
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

    public function test_notification_channels_returns_the_configured_channels_with_their_labels(): void
    {
        config([
            'notification-preferences.channels' => [
                'mail' => ['label' => 'Email'],
                'ntfy' => ['label' => 'Ntfy'],
            ],
        ]);

        $this->assertSame(
            [
                'mail' => ['label' => 'Email'],
                'ntfy' => ['label' => 'Ntfy'],
            ],
            $this->catalog()->notificationChannels(),
        );
    }

    public function test_notification_channels_is_empty_when_no_channels_are_configured(): void
    {
        config(['notification-preferences.channels' => []]);

        $this->assertSame([], $this->catalog()->notificationChannels());
    }

    public function test_available_channels_returns_the_global_channels(): void
    {
        $this->assertSame(['mail', 'ntfy'], $this->catalog()->availableNotificationChannels());
    }

    public function test_required_channels_returns_the_global_channels(): void
    {
        $this->assertSame(['app'], $this->catalog()->requiredNotificationChannels());
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

    public function test_the_config_provider_resolves_types_per_notifiable(): void
    {
        $this->assertInstanceOf(NotificationCatalogForNotifiable::class, $this->catalog());
    }

    public function test_notification_types_for_returns_every_type_when_no_guard_is_configured(): void
    {
        $this->assertSame(
            $this->catalog()->notificationTypes(),
            $this->guardedCatalog()->notificationTypesFor(new stdClass),
        );
    }

    public function test_notification_types_for_returns_every_type_when_the_guard_is_null(): void
    {
        config(['notification-preferences.guard' => null]);

        $this->assertSame(
            ['login', 'backup_failed'],
            array_keys($this->guardedCatalog()->notificationTypesFor(new stdClass)),
        );
    }

    public function test_notification_types_for_removes_types_the_guard_denies(): void
    {
        config([
            'notification-preferences.guard' => ConfigDrivenGuard::class,
            'notification-preferences.test_guard_allowed_types' => ['login'],
        ]);

        $this->assertSame(
            ['login'],
            array_keys($this->guardedCatalog()->notificationTypesFor(new stdClass)),
        );
    }

    public function test_notification_types_for_keeps_types_the_guard_allows(): void
    {
        config([
            'notification-preferences.guard' => ConfigDrivenGuard::class,
            'notification-preferences.test_guard_allowed_types' => ['login', 'backup_failed'],
        ]);

        $this->assertSame(
            ['login', 'backup_failed'],
            array_keys($this->guardedCatalog()->notificationTypesFor(new stdClass)),
        );
    }

    public function test_notification_types_for_passes_the_notification_type_to_the_guard(): void
    {
        config([
            'notification-preferences.guard' => ConfigDrivenGuard::class,
            'notification-preferences.test_guard_allowed_types' => ['backup_failed'],
        ]);

        $this->assertSame(
            ['backup_failed'],
            array_keys($this->guardedCatalog()->notificationTypesFor(new stdClass)),
        );
    }

    public function test_notification_types_for_passes_the_notifiable_to_the_guard(): void
    {
        config(['notification-preferences.guard' => NotifiablePropertyGuard::class]);

        $this->assertSame(
            ['login', 'backup_failed'],
            array_keys($this->guardedCatalog()->notificationTypesFor((object) ['receives_notifications' => true])),
        );

        $this->assertSame(
            [],
            array_keys($this->guardedCatalog()->notificationTypesFor((object) ['receives_notifications' => false])),
        );
    }

    public function test_notification_types_for_resolves_the_contract_binding_when_the_guard_is_the_interface(): void
    {
        $this->app->bind(NotificationTypeGuard::class, ConfigDrivenGuard::class);
        config([
            'notification-preferences.guard' => NotificationTypeGuard::class,
            'notification-preferences.test_guard_allowed_types' => ['login'],
        ]);

        $this->assertSame(
            ['login'],
            array_keys($this->guardedCatalog()->notificationTypesFor(new stdClass)),
        );
    }

    public function test_notification_types_for_throws_when_the_guard_is_the_interface_without_a_binding(): void
    {
        config(['notification-preferences.guard' => NotificationTypeGuard::class]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The [notification-preferences.guard] configuration value is the [SameOldNick\NotificationPreferences\Contracts\NotificationTypeGuard] contract, but no implementation is bound to the container.');

        $this->guardedCatalog()->notificationTypesFor(new stdClass);
    }

    public function test_notification_types_for_throws_when_the_guard_is_not_a_class_string(): void
    {
        config(['notification-preferences.guard' => false]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The [notification-preferences.guard] configuration value must be a class-string implementing [SameOldNick\NotificationPreferences\Contracts\NotificationTypeGuard], bool given.');

        $this->guardedCatalog()->notificationTypesFor(new stdClass);
    }

    public function test_notification_types_for_throws_when_the_guard_does_not_implement_the_contract(): void
    {
        config(['notification-preferences.guard' => stdClass::class]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The [notification-preferences.guard] configuration value must be a class-string implementing [SameOldNick\NotificationPreferences\Contracts\NotificationTypeGuard], stdClass given.');

        $this->guardedCatalog()->notificationTypesFor(new stdClass);
    }

    private function catalog(): NotificationCatalog
    {
        return $this->app->make(NotificationCatalog::class);
    }

    private function guardedCatalog(): NotificationCatalogForNotifiable
    {
        $catalog = $this->catalog();

        $this->assertInstanceOf(NotificationCatalogForNotifiable::class, $catalog);

        return $catalog;
    }
}
