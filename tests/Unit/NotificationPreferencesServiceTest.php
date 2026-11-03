<?php

namespace SameOldNick\NotificationPreferences\Tests\Unit;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use SameOldNick\NotificationPreferences\Contracts\NotificationChannelProvider;
use SameOldNick\NotificationPreferences\Contracts\NotificationTypeProvider;
use SameOldNick\NotificationPreferences\Models\NotifiableNotificationPreference;
use SameOldNick\NotificationPreferences\Services\NotificationPreferencesService;
use SameOldNick\NotificationPreferences\Tests\Fixtures\Channel;
use SameOldNick\NotificationPreferences\Tests\Fixtures\NotificationType;
use SameOldNick\NotificationPreferences\Tests\TestCase;
use stdClass;
use Workbench\App\Models\User;

class NotificationPreferencesServiceTest extends TestCase
{
    /**
     * @var array<string, array{label: string, category: string}>
     */
    private const TYPES = [
        'login' => ['label' => 'Login', 'category' => 'Security'],
        'backup_failed' => ['label' => 'Backup Failed', 'category' => 'Backups'],
    ];

    public function test_get_preferences_builds_an_entry_for_every_type_and_channel_pair(): void
    {
        $notifiable = $this->createNotifiable();

        $preferences = $this->service(['mail', 'ntfy'])->getPreferencesForNotifiable($notifiable);

        $this->assertSame(
            ['mail:login', 'ntfy:login', 'mail:backup_failed', 'ntfy:backup_failed'],
            array_keys($preferences),
        );
    }

    public function test_get_preferences_returns_the_expected_entry_shape(): void
    {
        $notifiable = $this->createNotifiable();

        $preferences = $this->service(['mail'])->getPreferencesForNotifiable($notifiable);

        $this->assertSame([
            'enabled' => true,
            'key' => 'login',
            'label' => 'Login',
            'category' => 'Security',
            'channel' => 'mail',
        ], $preferences['mail:login']);
    }

    public function test_get_preferences_defaults_missing_preferences_to_enabled(): void
    {
        $notifiable = $this->createNotifiable();

        $preferences = $this->service(['mail', 'ntfy'])->getPreferencesForNotifiable($notifiable);

        foreach ($preferences as $key => $preference) {
            $this->assertTrue($preference['enabled'], "Expected [{$key}] to be enabled by default.");
        }
    }

    public function test_get_preferences_applies_a_stored_disabled_preference(): void
    {
        $notifiable = $this->createNotifiable();
        $this->createPreference($notifiable, 'login', 'mail', false);

        $preferences = $this->service(['mail', 'ntfy'])->getPreferencesForNotifiable($notifiable);

        $this->assertIsBool($preferences['mail:login']['enabled']);
        $this->assertFalse($preferences['mail:login']['enabled']);
        $this->assertTrue($preferences['ntfy:login']['enabled']);
    }

    public function test_get_preferences_applies_a_stored_enabled_preference(): void
    {
        $notifiable = $this->createNotifiable();
        $this->createPreference($notifiable, 'backup_failed', 'ntfy', true);

        $preferences = $this->service(['mail', 'ntfy'])->getPreferencesForNotifiable($notifiable);

        $this->assertTrue($preferences['ntfy:backup_failed']['enabled']);
    }

    public function test_get_preferences_ignores_preferences_for_unconfigured_types(): void
    {
        $notifiable = $this->createNotifiable();
        $this->createPreference($notifiable, 'legacy_type', 'mail', false);

        $preferences = $this->service(['mail', 'ntfy'])->getPreferencesForNotifiable($notifiable);

        $this->assertArrayNotHasKey('mail:legacy_type', $preferences);
    }

    public function test_get_preferences_ignores_preferences_for_unavailable_channels(): void
    {
        $notifiable = $this->createNotifiable();
        $this->createPreference($notifiable, 'login', 'slack', false);

        $preferences = $this->service(['mail', 'ntfy'])->getPreferencesForNotifiable($notifiable);

        $this->assertArrayNotHasKey('slack:login', $preferences);
    }

    public function test_get_preferences_does_not_apply_preferences_of_other_notifiables(): void
    {
        $notifiable = $this->createNotifiable();
        $otherNotifiable = $this->createNotifiable();
        $this->createPreference($otherNotifiable, 'login', 'mail', false);

        $preferences = $this->service(['mail'])->getPreferencesForNotifiable($notifiable);

        $this->assertTrue($preferences['mail:login']['enabled']);
    }

    public function test_get_preferences_does_not_apply_preferences_of_another_notifiable_type(): void
    {
        $notifiable = $this->createNotifiable();

        NotifiableNotificationPreference::create([
            'notifiable_type' => 'App\\Models\\Admin',
            'notifiable_id' => $notifiable->getKey(),
            'notification_type' => 'login',
            'channel' => 'mail',
            'enabled' => false,
        ]);

        $preferences = $this->service(['mail'])->getPreferencesForNotifiable($notifiable);

        $this->assertTrue($preferences['mail:login']['enabled']);
    }

    public function test_get_preferences_normalises_enum_backed_channels_and_types(): void
    {
        $notifiable = $this->notifiableWithPreferences([
            (object) [
                'channel' => Channel::Mail,
                'notification_type' => NotificationType::Login,
                'enabled' => false,
            ],
        ]);

        $preferences = $this->service(['mail', 'ntfy'])->getPreferencesForNotifiable($notifiable);

        $this->assertSame(['mail:login', 'ntfy:login', 'mail:backup_failed', 'ntfy:backup_failed'], array_keys($preferences));
        $this->assertFalse($preferences['mail:login']['enabled']);
        $this->assertTrue($preferences['ntfy:login']['enabled']);
    }

    public function test_get_preferences_returns_nothing_when_no_types_are_configured(): void
    {
        $notifiable = $this->createNotifiable();

        $this->assertSame([], $this->service(['mail', 'ntfy'], [])->getPreferencesForNotifiable($notifiable));
    }

    public function test_get_preferences_returns_nothing_when_no_channels_are_available(): void
    {
        $notifiable = $this->createNotifiable();

        $this->assertSame([], $this->service([], self::TYPES)->getPreferencesForNotifiable($notifiable));
    }

    public function test_get_preferences_returns_a_single_entry_when_channels_are_duplicated(): void
    {
        $notifiable = $this->createNotifiable();

        $preferences = $this->service(['mail', 'mail'], ['login' => self::TYPES['login']])
            ->getPreferencesForNotifiable($notifiable);

        $this->assertSame(['mail:login'], array_keys($preferences));
    }

    public function test_get_preferences_queries_the_channel_provider_once_per_type(): void
    {
        $notifiable = $this->createNotifiable();

        $channelProvider = $this->mock(NotificationChannelProvider::class);
        $channelProvider->expects('availableNotificationChannelsFor')
            ->twice()
            ->withArgs(fn (string $type, object $subject): bool => $subject === $notifiable && isset(self::TYPES[$type]))
            ->andReturn(['mail', 'ntfy']);

        $typeProvider = $this->mock(NotificationTypeProvider::class);
        $typeProvider->expects('notificationTypes')->once()->andReturn(self::TYPES);

        $service = new NotificationPreferencesService($channelProvider, $typeProvider);

        $service->getPreferencesForNotifiable($notifiable);
    }

    public function test_get_preferences_uses_the_channels_available_for_each_type(): void
    {
        $channelProvider = $this->mock(NotificationChannelProvider::class);
        $channelProvider->allows('availableNotificationChannelsFor')
            ->andReturnUsing(fn (string $type): array => $type === 'login' ? ['mail', 'ntfy'] : ['mail']);

        $typeProvider = $this->mock(NotificationTypeProvider::class);
        $typeProvider->allows('notificationTypes')->andReturn(self::TYPES);

        $preferences = (new NotificationPreferencesService($channelProvider, $typeProvider))
            ->getPreferencesForNotifiable($this->createNotifiable());

        $this->assertSame(
            ['mail:login', 'ntfy:login', 'mail:backup_failed'],
            array_keys($preferences),
        );
    }

    public function test_get_preferences_rejects_a_notifiable_without_preferences_support(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The provided notifiable does not support notification preferences.');

        $this->service(['mail'])->getPreferencesForNotifiable(new stdClass);
    }

    public function test_update_preferences_creates_a_disabled_preference(): void
    {
        $notifiable = $this->createNotifiable();

        $this->service()->updatePreferencesForNotifiable($notifiable, [
            'login' => ['channels' => ['mail' => false]],
        ]);

        $preference = $this->preferenceFor($notifiable, 'login', 'mail');

        $this->assertNotNull($preference);
        $this->assertFalse($preference->enabled);
    }

    public function test_update_preferences_creates_an_enabled_preference(): void
    {
        $notifiable = $this->createNotifiable();

        $this->service()->updatePreferencesForNotifiable($notifiable, [
            'login' => ['channels' => ['mail' => true]],
        ]);

        $this->assertTrue($this->preferenceFor($notifiable, 'login', 'mail')?->enabled);
    }

    public function test_update_preferences_stores_the_notifiable_morph_keys(): void
    {
        $notifiable = $this->createNotifiable();

        $this->service()->updatePreferencesForNotifiable($notifiable, [
            'login' => ['channels' => ['mail' => false]],
        ]);

        $preference = $this->preferenceFor($notifiable, 'login', 'mail');

        $this->assertSame($notifiable->getMorphClass(), $preference?->notifiable_type);
        $this->assertSame($notifiable->getKey(), $preference?->notifiable_id);
    }

    public function test_update_preferences_updates_an_existing_preference_without_duplicating_it(): void
    {
        $notifiable = $this->createNotifiable();
        $this->createPreference($notifiable, 'login', 'mail', false);

        $this->service()->updatePreferencesForNotifiable($notifiable, [
            'login' => ['channels' => ['mail' => true]],
        ]);

        $this->assertSame(1, $notifiable->notificationPreferences()->count());
        $this->assertTrue($this->preferenceFor($notifiable, 'login', 'mail')?->enabled);
    }

    public function test_update_preferences_does_not_touch_preferences_that_already_match(): void
    {
        $notifiable = $this->createNotifiable();
        $originalUpdatedAt = $this->createPreference($notifiable, 'login', 'mail', true)->updated_at;

        $this->travel(5)->minutes();

        $this->service()->updatePreferencesForNotifiable($notifiable, [
            'login' => ['channels' => ['mail' => true]],
        ]);

        $this->assertSame(1, $notifiable->notificationPreferences()->count());
        $this->assertEquals($originalUpdatedAt, $this->preferenceFor($notifiable, 'login', 'mail')?->updated_at);
    }

    public function test_update_preferences_handles_repeated_identical_payloads(): void
    {
        $notifiable = $this->createNotifiable();

        $preferences = ['login' => ['channels' => ['mail' => false]]];

        $this->service()->updatePreferencesForNotifiable($notifiable, $preferences);
        $this->service()->updatePreferencesForNotifiable($notifiable, $preferences);

        $this->assertSame(1, $notifiable->notificationPreferences()->count());
        $this->assertFalse($this->preferenceFor($notifiable, 'login', 'mail')?->enabled);
    }

    public function test_update_preferences_creates_a_row_per_type_and_channel(): void
    {
        $notifiable = $this->createNotifiable();

        $this->service()->updatePreferencesForNotifiable($notifiable, [
            'login' => ['channels' => ['mail' => false, 'ntfy' => false]],
            'backup_failed' => ['channels' => ['mail' => false, 'ntfy' => true]],
        ]);

        $this->assertSame(4, $notifiable->notificationPreferences()->count());
        $this->assertFalse($this->preferenceFor($notifiable, 'login', 'ntfy')?->enabled);
        $this->assertTrue($this->preferenceFor($notifiable, 'backup_failed', 'ntfy')?->enabled);
    }

    public function test_update_preferences_leaves_unsubmitted_channels_and_types_untouched(): void
    {
        $notifiable = $this->createNotifiable();
        $this->createPreference($notifiable, 'login', 'ntfy', false);
        $this->createPreference($notifiable, 'backup_failed', 'mail', false);

        $this->service()->updatePreferencesForNotifiable($notifiable, [
            'login' => ['channels' => ['mail' => true]],
        ]);

        $this->assertFalse($this->preferenceFor($notifiable, 'login', 'ntfy')?->enabled);
        $this->assertFalse($this->preferenceFor($notifiable, 'backup_failed', 'mail')?->enabled);
    }

    public function test_update_preferences_does_not_modify_other_notifiables(): void
    {
        $notifiable = $this->createNotifiable();
        $otherNotifiable = $this->createNotifiable();
        $this->createPreference($otherNotifiable, 'login', 'mail', true);

        $this->service()->updatePreferencesForNotifiable($notifiable, [
            'login' => ['channels' => ['mail' => false]],
        ]);

        $this->assertTrue($this->preferenceFor($otherNotifiable, 'login', 'mail')?->enabled);
    }

    public function test_update_preferences_persists_any_type_and_channel_without_validating_them(): void
    {
        $notifiable = $this->createNotifiable();

        $this->service()->updatePreferencesForNotifiable($notifiable, [
            'not_a_configured_type' => ['channels' => ['not_a_configured_channel' => false]],
        ]);

        $this->assertFalse($this->preferenceFor($notifiable, 'not_a_configured_type', 'not_a_configured_channel')?->enabled);
    }

    public function test_update_preferences_accepts_integer_flags_and_stores_booleans(): void
    {
        $notifiable = $this->createNotifiable();

        $this->service()->updatePreferencesForNotifiable($notifiable, [
            'login' => ['channels' => ['mail' => 1, 'ntfy' => 0]],
        ]);

        $this->assertTrue($this->preferenceFor($notifiable, 'login', 'mail')?->enabled);
        $this->assertFalse($this->preferenceFor($notifiable, 'login', 'ntfy')?->enabled);
    }

    public function test_update_preferences_ignores_an_empty_payload(): void
    {
        $notifiable = $this->createNotifiable();

        $this->service()->updatePreferencesForNotifiable($notifiable, []);

        $this->assertSame(0, $notifiable->notificationPreferences()->count());
    }

    public function test_update_preferences_ignores_a_type_with_no_channels(): void
    {
        $notifiable = $this->createNotifiable();

        $this->service()->updatePreferencesForNotifiable($notifiable, [
            'login' => ['channels' => []],
        ]);

        $this->assertSame(0, $notifiable->notificationPreferences()->count());
    }

    public function test_update_preferences_rejects_a_notifiable_without_preferences_support(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The provided notifiable does not support notification preferences.');

        $this->service()->updatePreferencesForNotifiable(new stdClass, [
            'login' => ['channels' => ['mail' => false]],
        ]);
    }

    /**
     * Build the service with controllable providers, bypassing the configuration.
     *
     * @param  list<string>  $availableChannels
     * @param  array<string, array{label: string, category: string}>|null  $types
     */
    private function service(array $availableChannels = ['mail', 'ntfy'], ?array $types = null): NotificationPreferencesService
    {
        $channelProvider = $this->mock(NotificationChannelProvider::class);
        $channelProvider->allows('availableNotificationChannelsFor')->andReturn($availableChannels);

        $typeProvider = $this->mock(NotificationTypeProvider::class);
        $typeProvider->allows('notificationTypes')->andReturn($types ?? self::TYPES);

        return new NotificationPreferencesService($channelProvider, $typeProvider);
    }

    /**
     * Build a notifiable that already holds loaded preference records.
     *
     * @param  list<object>  $preferences
     */
    private function notifiableWithPreferences(array $preferences): object
    {
        return new class(collect($preferences))
        {
            /**
             * @param  Collection<int, object>  $notificationPreferences
             */
            public function __construct(public Collection $notificationPreferences) {}

            /**
             * @return Collection<int, object>
             */
            public function notificationPreferences(): Collection
            {
                return $this->notificationPreferences;
            }
        };
    }

    private function preferenceFor(User $notifiable, string $type, string $channel): ?NotifiableNotificationPreference
    {
        return $notifiable->notificationPreferences()
            ->where('notification_type', $type)
            ->where('channel', $channel)
            ->first();
    }
}
