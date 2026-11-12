<?php

namespace SameOldNick\NotificationPreferences\Tests\Unit;

use SameOldNick\NotificationPreferences\Contracts\NotificationCatalog;
use SameOldNick\NotificationPreferences\Services\NotificationChannelResolver;
use SameOldNick\NotificationPreferences\Tests\TestCase;
use stdClass;

class NotificationChannelResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'notification-preferences.channels' => [
                'mail' => ['label' => 'Email', 'enabled_by_default' => true],
                'ntfy' => ['label' => 'Ntfy', 'enabled_by_default' => true],
                'database' => ['label' => 'Database', 'enabled_by_default' => true],
            ],
        ]);
    }

    public function test_returns_no_channels_when_the_notifiable_is_null(): void
    {
        $this->assertSame([], $this->resolver()->viaNotifiablePreferences(null, 'login', ['mail', 'ntfy']));
    }

    public function test_returns_no_channels_when_no_available_channels_are_given(): void
    {
        $notifiable = $this->createNotifiable();

        $this->assertSame([], $this->resolver()->viaNotifiablePreferences($notifiable, 'login', []));
    }

    public function test_returns_the_available_channels_when_the_notifiable_has_no_preferences_support(): void
    {
        $this->assertSame(
            ['mail', 'ntfy'],
            $this->resolver()->viaNotifiablePreferences(new stdClass, 'login', ['mail', 'ntfy']),
        );
    }

    public function test_returns_the_available_channels_reindexed_when_the_notifiable_has_no_preferences_support(): void
    {
        $this->assertSame(
            ['mail', 'ntfy'],
            $this->resolver()->viaNotifiablePreferences(new stdClass, 'login', [1 => 'mail', 4 => 'ntfy']),
        );
    }

    public function test_returns_all_available_channels_when_no_preferences_are_stored(): void
    {
        $notifiable = $this->createNotifiable();

        $this->assertSame(
            ['mail', 'ntfy'],
            $this->resolver()->viaNotifiablePreferences($notifiable, 'login', ['mail', 'ntfy']),
        );
    }

    public function test_drops_a_channel_that_is_explicitly_disabled(): void
    {
        $notifiable = $this->createNotifiable();
        $this->createPreference($notifiable, 'login', 'mail', false);

        $this->assertSame(
            ['ntfy'],
            $this->resolver()->viaNotifiablePreferences($notifiable, 'login', ['mail', 'ntfy']),
        );
    }

    public function test_drops_every_channel_that_is_explicitly_disabled(): void
    {
        $notifiable = $this->createNotifiable();
        $this->createPreference($notifiable, 'login', 'mail', false);
        $this->createPreference($notifiable, 'login', 'ntfy', false);

        $this->assertSame([], $this->resolver()->viaNotifiablePreferences($notifiable, 'login', ['mail', 'ntfy']));
    }

    public function test_keeps_a_channel_with_an_enabled_preference(): void
    {
        $notifiable = $this->createNotifiable();
        $this->createPreference($notifiable, 'login', 'mail', true);

        $this->assertSame(
            ['mail', 'ntfy'],
            $this->resolver()->viaNotifiablePreferences($notifiable, 'login', ['mail', 'ntfy']),
        );
    }

    public function test_keeps_a_channel_that_has_no_preference_row(): void
    {
        $notifiable = $this->createNotifiable();
        $this->createPreference($notifiable, 'login', 'mail', false);

        $this->assertSame(
            ['ntfy'],
            $this->resolver()->viaNotifiablePreferences($notifiable, 'login', ['mail', 'ntfy']),
        );
    }

    public function test_ignores_preferences_stored_for_other_types(): void
    {
        $notifiable = $this->createNotifiable();
        $this->createPreference($notifiable, 'backup_failed', 'mail', false);

        $this->assertSame(
            ['mail', 'ntfy'],
            $this->resolver()->viaNotifiablePreferences($notifiable, 'login', ['mail', 'ntfy']),
        );
    }

    public function test_ignores_preferences_stored_for_channels_that_are_not_available(): void
    {
        $notifiable = $this->createNotifiable();
        $this->createPreference($notifiable, 'login', 'slack', false);

        $this->assertSame(
            ['mail', 'ntfy'],
            $this->resolver()->viaNotifiablePreferences($notifiable, 'login', ['mail', 'ntfy']),
        );
    }

    public function test_ignores_preferences_of_other_notifiables(): void
    {
        $notifiable = $this->createNotifiable();
        $otherNotifiable = $this->createNotifiable();
        $this->createPreference($otherNotifiable, 'login', 'mail', false);

        $this->assertSame(
            ['mail', 'ntfy'],
            $this->resolver()->viaNotifiablePreferences($notifiable, 'login', ['mail', 'ntfy']),
        );
    }

    public function test_deduplicates_repeated_available_channels(): void
    {
        $notifiable = $this->createNotifiable();

        $this->assertSame(
            ['mail', 'ntfy'],
            $this->resolver()->viaNotifiablePreferences($notifiable, 'login', ['mail', 'mail', 'ntfy']),
        );
    }

    public function test_returns_a_reindexed_list_when_a_disabled_channel_is_removed(): void
    {
        $notifiable = $this->createNotifiable();
        $this->createPreference($notifiable, 'login', 'mail', false);

        $this->assertSame(
            ['ntfy'],
            $this->resolver()->viaNotifiablePreferences($notifiable, 'login', [0 => 'mail', 1 => 'ntfy']),
        );
    }

    public function test_with_defaults_appends_required_channels(): void
    {
        $notifiable = $this->createNotifiable();

        $this->assertSame(
            ['mail', 'ntfy', 'database'],
            $this->resolver()->viaNotifiablePreferencesWithRequiredChannels($notifiable, 'login', ['mail', 'ntfy'], ['database']),
        );
    }

    public function test_with_defaults_does_not_duplicate_a_required_channel(): void
    {
        $notifiable = $this->createNotifiable();

        $this->assertSame(
            ['mail', 'ntfy'],
            $this->resolver()->viaNotifiablePreferencesWithRequiredChannels($notifiable, 'login', ['mail', 'ntfy'], ['mail']),
        );
    }

    public function test_with_defaults_keeps_a_required_channel_even_when_it_is_disabled(): void
    {
        $notifiable = $this->createNotifiable();
        $this->createPreference($notifiable, 'login', 'database', false);

        $this->assertSame(
            ['mail', 'database'],
            $this->resolver()->viaNotifiablePreferencesWithRequiredChannels($notifiable, 'login', ['mail', 'database'], ['database']),
        );
    }

    public function test_with_defaults_returns_required_channels_when_no_channels_are_available(): void
    {
        $notifiable = $this->createNotifiable();

        $this->assertSame(
            ['database'],
            $this->resolver()->viaNotifiablePreferencesWithRequiredChannels($notifiable, 'login', [], ['database']),
        );
    }

    public function test_with_defaults_returns_available_and_required_channels_when_the_notifiable_has_no_preferences_support(): void
    {
        $this->assertSame(
            ['mail', 'database'],
            $this->resolver()->viaNotifiablePreferencesWithRequiredChannels(new stdClass, 'login', ['mail'], ['database']),
        );
    }

    public function test_with_defaults_deduplicates_repeated_required_channels(): void
    {
        $notifiable = $this->createNotifiable();

        $this->assertSame(
            ['mail', 'database'],
            $this->resolver()->viaNotifiablePreferencesWithRequiredChannels($notifiable, 'login', ['mail'], ['database', 'database']),
        );
    }

    public function test_drops_a_channel_that_is_disabled_by_default_when_no_preference_is_stored(): void
    {
        config(['notification-preferences.channels.ntfy.enabled_by_default' => false]);

        $notifiable = $this->createNotifiable();

        $this->assertSame(
            ['mail'],
            $this->resolver()->viaNotifiablePreferences($notifiable, 'login', ['mail', 'ntfy']),
        );
    }

    public function test_keeps_a_channel_that_is_disabled_by_default_when_the_notifiable_enabled_it(): void
    {
        config(['notification-preferences.channels.ntfy.enabled_by_default' => false]);

        $notifiable = $this->createNotifiable();
        $this->createPreference($notifiable, 'login', 'ntfy', true);

        $this->assertSame(
            ['mail', 'ntfy'],
            $this->resolver()->viaNotifiablePreferences($notifiable, 'login', ['mail', 'ntfy']),
        );
    }

    public function test_type_default_channels_override_the_channel_default(): void
    {
        config([
            'notification-preferences.types.login.default_channels' => ['mail'],
        ]);

        $notifiable = $this->createNotifiable();

        $this->assertSame(
            ['mail'],
            $this->resolver()->viaNotifiablePreferences($notifiable, 'login', ['mail', 'ntfy']),
        );
    }

    public function test_with_defaults_appends_a_required_channel_that_is_disabled_by_default(): void
    {
        config(['notification-preferences.channels.database.enabled_by_default' => false]);

        $notifiable = $this->createNotifiable();

        $this->assertSame(
            ['mail', 'database'],
            $this->resolver()->viaNotifiablePreferencesWithRequiredChannels($notifiable, 'login', ['mail', 'database'], ['database']),
        );
    }

    public function test_treats_channels_as_enabled_when_the_catalog_has_no_defaults(): void
    {
        $notifiable = $this->createNotifiable();

        $this->assertSame(
            ['mail'],
            (new NotificationChannelResolver($this->mock(NotificationCatalog::class)))
                ->viaNotifiablePreferences($notifiable, 'login', ['mail']),
        );
    }

    private function resolver(): NotificationChannelResolver
    {
        return $this->app->make(NotificationChannelResolver::class);
    }
}
