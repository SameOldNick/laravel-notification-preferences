<?php

namespace SameOldNick\NotificationPreferences\Tests\Unit;

use SameOldNick\NotificationPreferences\Services\NotificationChannelResolver;
use SameOldNick\NotificationPreferences\Tests\TestCase;
use stdClass;

class NotificationChannelResolverTest extends TestCase
{
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

    public function test_with_defaults_appends_default_channels(): void
    {
        $notifiable = $this->createNotifiable();

        $this->assertSame(
            ['mail', 'ntfy', 'app'],
            $this->resolver()->viaNotifiablePreferencesWithRequiredChannels($notifiable, 'login', ['mail', 'ntfy'], ['app']),
        );
    }

    public function test_with_defaults_does_not_duplicate_a_default_channel(): void
    {
        $notifiable = $this->createNotifiable();

        $this->assertSame(
            ['mail', 'ntfy'],
            $this->resolver()->viaNotifiablePreferencesWithRequiredChannels($notifiable, 'login', ['mail', 'ntfy'], ['mail']),
        );
    }

    public function test_with_defaults_keeps_a_default_channel_even_when_it_is_disabled(): void
    {
        $notifiable = $this->createNotifiable();
        $this->createPreference($notifiable, 'login', 'app', false);

        $this->assertSame(
            ['mail', 'app'],
            $this->resolver()->viaNotifiablePreferencesWithRequiredChannels($notifiable, 'login', ['mail', 'app'], ['app']),
        );
    }

    public function test_with_defaults_returns_defaults_when_no_channels_are_available(): void
    {
        $notifiable = $this->createNotifiable();

        $this->assertSame(
            ['app'],
            $this->resolver()->viaNotifiablePreferencesWithRequiredChannels($notifiable, 'login', [], ['app']),
        );
    }

    public function test_with_defaults_returns_available_and_default_channels_when_the_notifiable_has_no_preferences_support(): void
    {
        $this->assertSame(
            ['mail', 'app'],
            $this->resolver()->viaNotifiablePreferencesWithRequiredChannels(new stdClass, 'login', ['mail'], ['app']),
        );
    }

    public function test_with_defaults_deduplicates_repeated_default_channels(): void
    {
        $notifiable = $this->createNotifiable();

        $this->assertSame(
            ['mail', 'app'],
            $this->resolver()->viaNotifiablePreferencesWithRequiredChannels($notifiable, 'login', ['mail'], ['app', 'app']),
        );
    }

    private function resolver(): NotificationChannelResolver
    {
        return new NotificationChannelResolver;
    }
}
