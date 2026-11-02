<?php

namespace SameOldNick\NotificationPreferences\Tests\Feature;

use SameOldNick\NotificationPreferences\Models\NotifiableNotificationPreference;
use SameOldNick\NotificationPreferences\Tests\TestCase;

class NotificationPreferencesIndexTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'notifications.types' => [
                'login' => ['label' => 'Login', 'category' => 'Security'],
                'password_change' => ['label' => 'Password Change', 'category' => 'Security'],
                'backup_failed' => ['label' => 'Backup Failed', 'category' => 'Backups'],
            ],
            'notifications.available_channels' => ['mail', 'ntfy'],
        ]);
    }

    public function test_index_requires_an_authenticated_notifiable(): void
    {
        // Desired: guests are rejected with 401. Currently a null user reaches the
        // service and triggers a TypeError (500) because the routes carry no auth middleware.
        $this->getJson(route('notification-preferences.index'))->assertUnauthorized();
    }

    public function test_index_returns_json(): void
    {
        $notifiable = $this->createNotifiable();

        $this->actingAs($notifiable)
            ->getJson(route('notification-preferences.index'))
            ->assertOk()
            ->assertHeader('content-type', 'application/json');
    }

    public function test_index_returns_an_entry_for_every_type_and_channel_pair(): void
    {
        $notifiable = $this->createNotifiable();

        $response = $this->actingAs($notifiable)->getJson(route('notification-preferences.index'));

        $response->assertOk();

        $this->assertEqualsCanonicalizing(
            [
                'mail:login',
                'ntfy:login',
                'mail:password_change',
                'ntfy:password_change',
                'mail:backup_failed',
                'ntfy:backup_failed',
            ],
            array_keys($response->json()),
        );
    }

    public function test_index_keys_entries_by_channel_then_type(): void
    {
        $notifiable = $this->createNotifiable();

        $response = $this->actingAs($notifiable)->getJson(route('notification-preferences.index'));

        $response->assertOk();

        foreach (array_keys($response->json()) as $key) {
            [$channel, $type] = explode(':', $key, 2);

            $this->assertContains($channel, ['mail', 'ntfy']);
            $this->assertContains($type, ['login', 'password_change', 'backup_failed']);
        }
    }

    public function test_index_returns_key_label_category_and_channel_metadata(): void
    {
        $notifiable = $this->createNotifiable();

        $response = $this->actingAs($notifiable)->getJson(route('notification-preferences.index'));

        $response->assertOk()
            ->assertJsonPath('ntfy:backup_failed.key', 'backup_failed')
            ->assertJsonPath('ntfy:backup_failed.label', 'Backup Failed')
            ->assertJsonPath('ntfy:backup_failed.category', 'Backups')
            ->assertJsonPath('ntfy:backup_failed.channel', 'ntfy')
            ->assertJsonPath('ntfy:backup_failed.enabled', true);

        $this->assertEqualsCanonicalizing(
            ['enabled', 'key', 'label', 'category', 'channel'],
            array_keys($response->json('ntfy:backup_failed')),
        );
    }

    public function test_index_returns_enabled_as_a_real_boolean(): void
    {
        $notifiable = $this->createNotifiable();

        $enabled = $this->actingAs($notifiable)
            ->getJson(route('notification-preferences.index'))
            ->json('mail:login.enabled');

        $this->assertIsBool($enabled);
        $this->assertTrue($enabled);
    }

    public function test_index_defaults_types_without_a_stored_preference_to_enabled(): void
    {
        $notifiable = $this->createNotifiable();

        $this->createPreference($notifiable, 'login', 'mail', false);

        $this->actingAs($notifiable)
            ->getJson(route('notification-preferences.index'))
            ->assertOk()
            ->assertJsonPath('mail:login.enabled', false)
            ->assertJsonPath('ntfy:login.enabled', true)
            ->assertJsonPath('mail:password_change.enabled', true)
            ->assertJsonPath('ntfy:backup_failed.enabled', true);
    }

    public function test_index_reflects_a_stored_enabled_preference(): void
    {
        $notifiable = $this->createNotifiable();

        $this->createPreference($notifiable, 'password_change', 'ntfy', true);

        $this->actingAs($notifiable)
            ->getJson(route('notification-preferences.index'))
            ->assertOk()
            ->assertJsonPath('ntfy:password_change.enabled', true);
    }

    public function test_index_returns_a_single_entry_per_pair_when_a_preference_is_stored(): void
    {
        $notifiable = $this->createNotifiable();

        $this->createPreference($notifiable, 'login', 'mail', false);
        $this->createPreference($notifiable, 'login', 'ntfy', false);

        $response = $this->actingAs($notifiable)->getJson(route('notification-preferences.index'));

        $response->assertOk();

        $this->assertCount(6, $response->json());
    }

    public function test_index_does_not_apply_another_notifiables_preferences(): void
    {
        $notifiable = $this->createNotifiable();
        $otherNotifiable = $this->createNotifiable();

        $this->createPreference($otherNotifiable, 'login', 'mail', false);

        $this->actingAs($notifiable)
            ->getJson(route('notification-preferences.index'))
            ->assertOk()
            ->assertJsonPath('mail:login.enabled', true);
    }

    public function test_index_does_not_apply_preferences_stored_for_another_notifiable_type(): void
    {
        $notifiable = $this->createNotifiable();

        NotifiableNotificationPreference::create([
            'notifiable_type' => 'App\\Models\\Admin',
            'notifiable_id' => $notifiable->getKey(),
            'notification_type' => 'login',
            'channel' => 'mail',
            'enabled' => false,
        ]);

        $this->actingAs($notifiable)
            ->getJson(route('notification-preferences.index'))
            ->assertOk()
            ->assertJsonPath('mail:login.enabled', true);
    }

    public function test_index_ignores_preferences_for_types_that_are_no_longer_configured(): void
    {
        $notifiable = $this->createNotifiable();

        $this->createPreference($notifiable, 'legacy_type', 'mail', false);

        $response = $this->actingAs($notifiable)->getJson(route('notification-preferences.index'));

        $response->assertOk()->assertJsonMissingPath('mail:legacy_type');

        $this->assertCount(6, $response->json());
    }

    public function test_index_ignores_preferences_for_channels_that_are_not_available(): void
    {
        $notifiable = $this->createNotifiable();

        $this->createPreference($notifiable, 'login', 'slack', false);

        $response = $this->actingAs($notifiable)->getJson(route('notification-preferences.index'));

        $response->assertOk()->assertJsonMissingPath('slack:login');
    }

    public function test_index_only_returns_configured_available_channels(): void
    {
        config(['notifications.available_channels' => ['mail']]);

        $notifiable = $this->createNotifiable();

        $response = $this->actingAs($notifiable)->getJson(route('notification-preferences.index'));

        $response->assertOk();

        $this->assertEqualsCanonicalizing(
            ['mail:login', 'mail:password_change', 'mail:backup_failed'],
            array_keys($response->json()),
        );
    }

    public function test_index_includes_types_without_any_stored_preferences(): void
    {
        $notifiable = $this->createNotifiable();

        $this->createPreference($notifiable, 'login', 'mail', true);
        $this->createPreference($notifiable, 'login', 'ntfy', true);

        $response = $this->actingAs($notifiable)->getJson(route('notification-preferences.index'));

        $response->assertOk()
            ->assertJsonPath('mail:backup_failed.category', 'Backups')
            ->assertJsonPath('ntfy:password_change.label', 'Password Change');
    }

    public function test_index_returns_an_empty_payload_when_no_types_are_configured(): void
    {
        config(['notifications.types' => []]);

        $notifiable = $this->createNotifiable();

        $this->actingAs($notifiable)
            ->getJson(route('notification-preferences.index'))
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_index_returns_an_empty_payload_when_no_channels_are_available(): void
    {
        config(['notifications.available_channels' => []]);

        $notifiable = $this->createNotifiable();

        $this->actingAs($notifiable)
            ->getJson(route('notification-preferences.index'))
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_index_reads_the_type_and_channel_providers_at_request_time(): void
    {
        $notifiable = $this->createNotifiable();

        $this->actingAs($notifiable)
            ->getJson(route('notification-preferences.index'))
            ->assertOk()
            ->assertJsonCount(6);

        config([
            'notifications.types' => [
                'login' => ['label' => 'Sign In', 'category' => 'Account'],
            ],
            'notifications.available_channels' => ['mail'],
        ]);

        $this->actingAs($notifiable)
            ->getJson(route('notification-preferences.index'))
            ->assertOk()
            ->assertExactJson([
                'mail:login' => [
                    'enabled' => true,
                    'key' => 'login',
                    'label' => 'Sign In',
                    'category' => 'Account',
                    'channel' => 'mail',
                ],
            ]);
    }
}
