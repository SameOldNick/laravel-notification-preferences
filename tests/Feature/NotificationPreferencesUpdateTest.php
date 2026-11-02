<?php

namespace SameOldNick\NotificationPreferences\Tests\Feature;

use SameOldNick\NotificationPreferences\Models\NotifiableNotificationPreference;
use SameOldNick\NotificationPreferences\Tests\TestCase;
use Workbench\App\Models\User;

class NotificationPreferencesUpdateTest extends TestCase
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

    public function test_update_requires_an_authenticated_notifiable(): void
    {
        // Desired: guests are rejected with 401. Currently a null user reaches the
        // service and triggers a TypeError (500) because the routes carry no auth middleware.
        $this->postJson(route('notification-preferences.update'), $this->payload([
            'login' => ['mail' => false],
        ]))->assertUnauthorized();
    }

    public function test_update_route_rejects_unsupported_methods(): void
    {
        $notifiable = $this->createNotifiable();

        $this->actingAs($notifiable)
            ->putJson(route('notification-preferences.update'), $this->payload([
                'login' => ['mail' => false],
            ]))
            ->assertMethodNotAllowed();
    }

    public function test_update_returns_a_success_message(): void
    {
        $notifiable = $this->createNotifiable();

        $this->actingAs($notifiable)
            ->postJson(route('notification-preferences.update'), $this->payload([
                'login' => ['mail' => false],
            ]))
            ->assertOk()
            ->assertExactJson(['message' => 'Notification preferences updated successfully.']);
    }

    public function test_update_persists_a_disabled_preference(): void
    {
        $notifiable = $this->createNotifiable();

        $this->actingAs($notifiable)
            ->postJson(route('notification-preferences.update'), $this->payload([
                'login' => ['mail' => false],
            ]))
            ->assertOk();

        $preference = $this->preferenceFor($notifiable, 'login', 'mail');

        $this->assertNotNull($preference);
        $this->assertFalse($preference->enabled);
        $this->assertSame($notifiable->getMorphClass(), $preference->notifiable_type);
        $this->assertSame($notifiable->getKey(), $preference->notifiable_id);
    }

    public function test_update_persists_an_enabled_preference(): void
    {
        $notifiable = $this->createNotifiable();

        $this->actingAs($notifiable)
            ->postJson(route('notification-preferences.update'), $this->payload([
                'login' => ['mail' => true],
            ]))
            ->assertOk();

        $this->assertSame(1, $notifiable->notificationPreferences()->count());
        $this->assertTrue($this->preferenceFor($notifiable, 'login', 'mail')?->enabled);
    }

    public function test_update_updates_an_existing_preference_without_creating_a_duplicate(): void
    {
        $notifiable = $this->createNotifiable();
        $this->createPreference($notifiable, 'login', 'mail', false);

        $this->actingAs($notifiable)
            ->postJson(route('notification-preferences.update'), $this->payload([
                'login' => ['mail' => true],
            ]))
            ->assertOk();

        $this->assertSame(1, $notifiable->notificationPreferences()->count());
        $this->assertTrue($this->preferenceFor($notifiable, 'login', 'mail')?->enabled);
    }

    public function test_update_does_not_touch_preferences_that_already_match(): void
    {
        $notifiable = $this->createNotifiable();
        $originalUpdatedAt = $this->createPreference($notifiable, 'login', 'mail', true)->updated_at;

        $this->travel(5)->minutes();

        $this->actingAs($notifiable)
            ->postJson(route('notification-preferences.update'), $this->payload([
                'login' => ['mail' => true],
            ]))
            ->assertOk();

        $this->assertSame(1, $notifiable->notificationPreferences()->count());
        $this->assertEquals(
            $originalUpdatedAt,
            $this->preferenceFor($notifiable, 'login', 'mail')?->updated_at,
        );
    }

    public function test_update_is_idempotent_when_the_same_payload_is_submitted_twice(): void
    {
        $notifiable = $this->createNotifiable();

        $payload = $this->payload(['login' => ['mail' => false, 'ntfy' => true]]);

        $this->actingAs($notifiable)->postJson(route('notification-preferences.update'), $payload)->assertOk();
        $this->actingAs($notifiable)->postJson(route('notification-preferences.update'), $payload)->assertOk();

        $this->assertSame(2, $notifiable->notificationPreferences()->count());
        $this->assertFalse($this->preferenceFor($notifiable, 'login', 'mail')?->enabled);
        $this->assertTrue($this->preferenceFor($notifiable, 'login', 'ntfy')?->enabled);
    }

    public function test_update_persists_multiple_types_and_channels_in_one_request(): void
    {
        $notifiable = $this->createNotifiable();

        $this->actingAs($notifiable)
            ->postJson(route('notification-preferences.update'), $this->payload([
                'login' => ['mail' => false, 'ntfy' => false],
                'backup_failed' => ['mail' => false, 'ntfy' => true],
            ]))
            ->assertOk();

        $this->assertSame(4, $notifiable->notificationPreferences()->count());
        $this->assertFalse($this->preferenceFor($notifiable, 'login', 'mail')?->enabled);
        $this->assertFalse($this->preferenceFor($notifiable, 'login', 'ntfy')?->enabled);
        $this->assertFalse($this->preferenceFor($notifiable, 'backup_failed', 'mail')?->enabled);
        $this->assertTrue($this->preferenceFor($notifiable, 'backup_failed', 'ntfy')?->enabled);
    }

    public function test_update_leaves_channels_that_were_not_submitted_untouched(): void
    {
        $notifiable = $this->createNotifiable();
        $this->createPreference($notifiable, 'login', 'mail', false);
        $this->createPreference($notifiable, 'login', 'ntfy', false);

        $this->actingAs($notifiable)
            ->postJson(route('notification-preferences.update'), $this->payload([
                'login' => ['mail' => true],
            ]))
            ->assertOk();

        $this->assertSame(2, $notifiable->notificationPreferences()->count());
        $this->assertTrue($this->preferenceFor($notifiable, 'login', 'mail')?->enabled);
        $this->assertFalse($this->preferenceFor($notifiable, 'login', 'ntfy')?->enabled);
    }

    public function test_update_leaves_types_that_were_not_submitted_untouched(): void
    {
        $notifiable = $this->createNotifiable();
        $this->createPreference($notifiable, 'password_change', 'mail', false);

        $this->actingAs($notifiable)
            ->postJson(route('notification-preferences.update'), $this->payload([
                'login' => ['mail' => true],
            ]))
            ->assertOk();

        $this->assertFalse($this->preferenceFor($notifiable, 'password_change', 'mail')?->enabled);
    }

    public function test_update_does_not_modify_another_notifiables_preferences(): void
    {
        $notifiable = $this->createNotifiable();
        $otherNotifiable = $this->createNotifiable();

        $this->createPreference($otherNotifiable, 'login', 'mail', true);

        $this->actingAs($notifiable)
            ->postJson(route('notification-preferences.update'), $this->payload([
                'login' => ['mail' => false],
            ]))
            ->assertOk();

        $this->assertTrue($this->preferenceFor($otherNotifiable, 'login', 'mail')?->enabled);
        $this->assertFalse($this->preferenceFor($notifiable, 'login', 'mail')?->enabled);
    }

    public function test_update_changes_are_visible_on_the_index_route(): void
    {
        $notifiable = $this->createNotifiable();

        $this->actingAs($notifiable)
            ->postJson(route('notification-preferences.update'), $this->payload([
                'login' => ['mail' => false],
            ]))
            ->assertOk();

        $this->actingAs($notifiable)
            ->getJson(route('notification-preferences.index'))
            ->assertOk()
            ->assertJsonPath('mail:login.enabled', false)
            ->assertJsonPath('ntfy:login.enabled', true);
    }

    public function test_update_accepts_boolean_coercible_integers(): void
    {
        $notifiable = $this->createNotifiable();

        $this->actingAs($notifiable)
            ->postJson(route('notification-preferences.update'), $this->payload([
                'login' => ['mail' => 1, 'ntfy' => 0],
            ]))
            ->assertOk();

        $this->assertTrue($this->preferenceFor($notifiable, 'login', 'mail')?->enabled);
        $this->assertFalse($this->preferenceFor($notifiable, 'login', 'ntfy')?->enabled);
    }

    public function test_update_accepts_boolean_coercible_strings(): void
    {
        $notifiable = $this->createNotifiable();

        $this->actingAs($notifiable)
            ->postJson(route('notification-preferences.update'), $this->payload([
                'login' => ['mail' => '1', 'ntfy' => '0'],
            ]))
            ->assertOk();

        $this->assertTrue($this->preferenceFor($notifiable, 'login', 'mail')?->enabled);
        $this->assertFalse($this->preferenceFor($notifiable, 'login', 'ntfy')?->enabled);
    }

    public function test_update_rejects_a_missing_types_key(): void
    {
        $notifiable = $this->createNotifiable();

        $this->actingAs($notifiable)
            ->postJson(route('notification-preferences.update'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['types']);
    }

    public function test_update_rejects_an_empty_types_array(): void
    {
        $notifiable = $this->createNotifiable();

        $this->actingAs($notifiable)
            ->postJson(route('notification-preferences.update'), ['types' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['types']);
    }

    public function test_update_rejects_a_non_array_types_value(): void
    {
        $notifiable = $this->createNotifiable();

        $this->actingAs($notifiable)
            ->postJson(route('notification-preferences.update'), ['types' => 'login'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['types']);
    }

    public function test_update_rejects_a_list_style_types_value(): void
    {
        $notifiable = $this->createNotifiable();

        $this->actingAs($notifiable)
            ->postJson(route('notification-preferences.update'), [
                'types' => [['channels' => ['mail' => false]]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['types']);
    }

    public function test_update_rejects_an_unknown_notification_type(): void
    {
        $notifiable = $this->createNotifiable();

        $this->actingAs($notifiable)
            ->postJson(route('notification-preferences.update'), $this->payload([
                'not_a_real_type' => ['mail' => false],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['types']);
    }

    public function test_update_rejects_notification_type_keys_case_sensitively(): void
    {
        $notifiable = $this->createNotifiable();

        $this->actingAs($notifiable)
            ->postJson(route('notification-preferences.update'), $this->payload([
                'Login' => ['mail' => false],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['types']);
    }

    public function test_update_rejects_a_type_without_channels(): void
    {
        $notifiable = $this->createNotifiable();

        $this->actingAs($notifiable)
            ->postJson(route('notification-preferences.update'), ['types' => ['login' => []]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['types.login.channels']);
    }

    public function test_update_rejects_a_type_with_an_empty_channels_array(): void
    {
        $notifiable = $this->createNotifiable();

        $this->actingAs($notifiable)
            ->postJson(route('notification-preferences.update'), $this->payload(['login' => []]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['types.login.channels']);
    }

    public function test_update_rejects_a_non_array_channels_value(): void
    {
        $notifiable = $this->createNotifiable();

        $this->actingAs($notifiable)
            ->postJson(route('notification-preferences.update'), [
                'types' => ['login' => ['channels' => 'mail']],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['types.login.channels']);
    }

    public function test_update_rejects_an_unknown_channel(): void
    {
        $notifiable = $this->createNotifiable();

        $this->actingAs($notifiable)
            ->postJson(route('notification-preferences.update'), $this->payload([
                'login' => ['slack' => false],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['types.login.channels']);
    }

    public function test_update_rejects_any_channel_when_no_channels_are_available(): void
    {
        // Desired: with nothing available, no channel should be accepted. Currently the
        // empty allow-list degrades to an unconstrained "array" rule, so any channel persists.
        config(['notifications.available_channels' => []]);

        $notifiable = $this->createNotifiable();

        $this->actingAs($notifiable)
            ->postJson(route('notification-preferences.update'), $this->payload([
                'login' => ['mail' => false],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['types.login.channels']);
    }

    public function test_update_rejects_a_null_channel_value(): void
    {
        $notifiable = $this->createNotifiable();

        $this->actingAs($notifiable)
            ->postJson(route('notification-preferences.update'), $this->payload([
                'login' => ['mail' => null],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['types.login.channels.mail']);
    }

    public function test_update_rejects_a_non_boolean_channel_value(): void
    {
        $notifiable = $this->createNotifiable();

        $this->actingAs($notifiable)
            ->postJson(route('notification-preferences.update'), $this->payload([
                'login' => ['mail' => 'yes'],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['types.login.channels.mail']);
    }

    public function test_update_rejects_an_integer_outside_the_boolean_domain(): void
    {
        $notifiable = $this->createNotifiable();

        $this->actingAs($notifiable)
            ->postJson(route('notification-preferences.update'), $this->payload([
                'login' => ['mail' => 2],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['types.login.channels.mail']);
    }

    public function test_update_rejects_a_nested_array_as_a_channel_value(): void
    {
        $notifiable = $this->createNotifiable();

        $this->actingAs($notifiable)
            ->postJson(route('notification-preferences.update'), $this->payload([
                'login' => ['mail' => ['enabled' => false]],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['types.login.channels.mail']);
    }

    public function test_update_rejects_every_type_when_no_types_are_configured(): void
    {
        config(['notifications.types' => []]);

        $notifiable = $this->createNotifiable();

        $this->actingAs($notifiable)
            ->postJson(route('notification-preferences.update'), $this->payload([
                'login' => ['mail' => false],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['types']);
    }

    public function test_update_does_not_persist_anything_when_validation_fails(): void
    {
        $notifiable = $this->createNotifiable();

        $this->actingAs($notifiable)
            ->postJson(route('notification-preferences.update'), $this->payload([
                'login' => ['mail' => false],
                'not_a_real_type' => ['mail' => false],
            ]))
            ->assertUnprocessable();

        $this->assertDatabaseCount('notifiable_notification_preferences', 0);
    }

    public function test_update_returns_validation_errors_as_json(): void
    {
        $notifiable = $this->createNotifiable();

        $response = $this->actingAs($notifiable)
            ->postJson(route('notification-preferences.update'), $this->payload([
                'login' => ['slack' => false],
            ]))
            ->assertUnprocessable();

        $this->assertSame(
            ['types.login.channels'],
            array_keys($response->json('errors')),
        );
    }

    public function test_update_redirects_back_with_errors_for_html_requests(): void
    {
        $notifiable = $this->createNotifiable();

        $this->actingAs($notifiable)
            ->post(route('notification-preferences.update'), $this->payload([
                'not_a_real_type' => ['mail' => false],
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors(['types']);

        $this->assertDatabaseCount('notifiable_notification_preferences', 0);
    }

    public function test_update_does_not_partially_apply_a_payload_with_one_invalid_channel(): void
    {
        $notifiable = $this->createNotifiable();

        $this->actingAs($notifiable)
            ->postJson(route('notification-preferences.update'), $this->payload([
                'login' => ['mail' => false, 'slack' => false],
            ]))
            ->assertUnprocessable();

        $this->assertDatabaseCount('notifiable_notification_preferences', 0);
    }

    /**
     * @param  array<string, array<string, mixed>>  $channelsByType
     * @return array{types: array<string, array{channels: array<string, mixed>}>}
     */
    private function payload(array $channelsByType): array
    {
        return [
            'types' => array_map(
                static fn (array $channels): array => ['channels' => $channels],
                $channelsByType,
            ),
        ];
    }

    private function preferenceFor(User $notifiable, string $type, string $channel): ?NotifiableNotificationPreference
    {
        return $notifiable->notificationPreferences()
            ->where('notification_type', $type)
            ->where('channel', $channel)
            ->first();
    }
}
