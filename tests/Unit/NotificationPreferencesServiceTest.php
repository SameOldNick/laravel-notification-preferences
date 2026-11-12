<?php

namespace SameOldNick\NotificationPreferences\Tests\Unit;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;
use InvalidArgumentException;
use SameOldNick\NotificationPreferences\Contracts\NotificationCatalog;
use SameOldNick\NotificationPreferences\Contracts\NotificationCatalogForNotifiable;
use SameOldNick\NotificationPreferences\Contracts\NotificationChannelDefaults;
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

    public function test_get_preferences_translates_labels_and_categories_using_translation_keys(): void
    {
        Lang::addLines([
            'messages.notifications.login.label' => 'Sign In',
            'messages.notifications.login.category' => 'Account Security',
        ], 'en');

        $types = [
            'login' => [
                'label' => 'messages.notifications.login.label',
                'category' => 'messages.notifications.login.category',
            ],
        ];

        $preferences = $this->service(['mail', 'ntfy'], $types)->getPreferencesForNotifiable($this->createNotifiable());

        foreach (['mail:login', 'ntfy:login'] as $key) {
            $this->assertSame('Sign In', $preferences[$key]['label']);
            $this->assertSame('Account Security', $preferences[$key]['category']);
        }
    }

    public function test_get_preferences_translates_labels_and_categories_for_the_active_locale(): void
    {
        Lang::addLines([
            'messages.notifications.login.label' => 'Sign In',
            'messages.notifications.login.category' => 'Account Security',
        ], 'en');

        Lang::addLines([
            'messages.notifications.login.label' => 'Se connecter',
            'messages.notifications.login.category' => 'Sécurité du compte',
        ], 'fr');

        $types = [
            'login' => [
                'label' => 'messages.notifications.login.label',
                'category' => 'messages.notifications.login.category',
            ],
        ];

        $this->app->setLocale('fr');

        $preferences = $this->service(['mail'], $types)->getPreferencesForNotifiable($this->createNotifiable());

        $this->assertSame('Se connecter', $preferences['mail:login']['label']);
        $this->assertSame('Sécurité du compte', $preferences['mail:login']['category']);
    }

    public function test_get_preferences_falls_back_to_the_configured_label_and_category_when_no_translation_exists(): void
    {
        $types = [
            'login' => ['label' => 'Login', 'category' => 'Security'],
            'backup_failed' => ['label' => 'messages.notifications.backup_failed.label', 'category' => 'messages.notifications.backup_failed.category'],
        ];

        $preferences = $this->service(['mail'], $types)->getPreferencesForNotifiable($this->createNotifiable());

        $this->assertSame('Login', $preferences['mail:login']['label']);
        $this->assertSame('Security', $preferences['mail:login']['category']);
        $this->assertSame('messages.notifications.backup_failed.label', $preferences['mail:backup_failed']['label']);
        $this->assertSame('messages.notifications.backup_failed.category', $preferences['mail:backup_failed']['category']);
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

    public function test_get_preferences_uses_the_channel_defaults_from_the_catalog(): void
    {
        $catalog = $this->mock(NotificationCatalog::class.', '.NotificationChannelDefaults::class);
        $catalog->allows('notificationTypes')->andReturn(self::TYPES);
        $catalog->allows('availableNotificationChannelsFor')->andReturn(['mail', 'ntfy']);
        $catalog->allows('channelEnabledByDefault')->andReturnUsing(
            fn (string $notificationType, string $channel): bool => $channel === 'mail',
        );

        $preferences = (new NotificationPreferencesService($catalog))
            ->getPreferencesForNotifiable($this->createNotifiable());

        $this->assertTrue($preferences['mail:login']['enabled']);
        $this->assertFalse($preferences['ntfy:login']['enabled']);
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

        $catalog = $this->mock(NotificationCatalog::class);
        $catalog->expects('availableNotificationChannelsFor')
            ->twice()
            ->withArgs(fn (string $type, object $subject): bool => $subject === $notifiable && isset(self::TYPES[$type]))
            ->andReturn(['mail', 'ntfy']);
        $catalog->expects('notificationTypes')->once()->andReturn(self::TYPES);

        $service = new NotificationPreferencesService($catalog);

        $service->getPreferencesForNotifiable($notifiable);
    }

    public function test_get_preferences_uses_the_channels_available_for_each_type(): void
    {
        $catalog = $this->mock(NotificationCatalog::class);
        $catalog->allows('availableNotificationChannelsFor')
            ->andReturnUsing(fn (string $type): array => $type === 'login' ? ['mail', 'ntfy'] : ['mail']);
        $catalog->allows('notificationTypes')->andReturn(self::TYPES);

        $preferences = (new NotificationPreferencesService($catalog))
            ->getPreferencesForNotifiable($this->createNotifiable());

        $this->assertSame(
            ['mail:login', 'ntfy:login', 'mail:backup_failed'],
            array_keys($preferences),
        );
    }

    public function test_get_notification_types_for_notifiable_uses_the_notifiable_aware_catalog(): void
    {
        $notifiable = $this->createNotifiable();
        $guardedTypes = ['login' => self::TYPES['login']];

        $catalog = $this->mock(NotificationCatalogForNotifiable::class);
        $catalog->expects('notificationTypesFor')->once()->with($notifiable)->andReturn($guardedTypes);

        $this->assertSame(
            $guardedTypes,
            (new NotificationPreferencesService($catalog))->getNotificationTypesForNotifiable($notifiable),
        );
    }

    public function test_get_notification_types_for_notifiable_falls_back_to_the_unscoped_types(): void
    {
        $notifiable = $this->createNotifiable();

        $catalog = $this->mock(NotificationCatalog::class);
        $catalog->expects('notificationTypes')->once()->andReturn(self::TYPES);

        $this->assertSame(
            self::TYPES,
            (new NotificationPreferencesService($catalog))->getNotificationTypesForNotifiable($notifiable),
        );
    }

    public function test_get_notification_types_for_notifiable_rejects_a_notifiable_without_preferences_support(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The provided notifiable does not support notification preferences.');

        $this->service()->getNotificationTypesForNotifiable(new stdClass);
    }

    public function test_get_preferences_omits_types_that_are_guarded_out(): void
    {
        $catalog = $this->mock(NotificationCatalogForNotifiable::class);
        $catalog->allows('notificationTypesFor')->andReturn(['login' => self::TYPES['login']]);
        $catalog->allows('availableNotificationChannelsFor')->andReturn(['mail', 'ntfy']);

        $preferences = (new NotificationPreferencesService($catalog))
            ->getPreferencesForNotifiable($this->createNotifiable());

        $this->assertSame(['mail:login', 'ntfy:login'], array_keys($preferences));
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

    public function test_update_preferences_rejects_a_type_that_is_not_in_the_catalog(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The notification type [not_a_configured_type] is not available for this notifiable.');

        $this->service()->updatePreferencesForNotifiable($this->createNotifiable(), [
            'not_a_configured_type' => ['channels' => ['mail' => false]],
        ]);
    }

    public function test_update_preferences_rejects_a_channel_that_is_not_available_for_the_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The notification channel [ntfy] is not available for notification type [login].');

        $this->service(['mail'])->updatePreferencesForNotifiable($this->createNotifiable(), [
            'login' => ['channels' => ['ntfy' => false]],
        ]);
    }

    public function test_update_preferences_rejects_a_type_that_is_guarded_out_for_the_notifiable(): void
    {
        $catalog = $this->mock(NotificationCatalogForNotifiable::class);
        $catalog->allows('notificationTypesFor')->andReturn(['login' => self::TYPES['login']]);
        $catalog->allows('availableNotificationChannelsFor')->andReturn(['mail']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The notification type [backup_failed] is not available for this notifiable.');

        (new NotificationPreferencesService($catalog))->updatePreferencesForNotifiable($this->createNotifiable(), [
            'backup_failed' => ['channels' => ['mail' => false]],
        ]);
    }

    public function test_update_preferences_accepts_a_required_channel_that_is_not_available(): void
    {
        $notifiable = $this->createNotifiable();

        $catalog = $this->mock(NotificationCatalog::class);
        $catalog->allows('notificationTypes')->andReturn(self::TYPES);
        $catalog->allows('availableNotificationChannelsFor')->andReturn(['mail']);
        $catalog->allows('requiredNotificationChannelsFor')->andReturn(['database']);

        (new NotificationPreferencesService($catalog))->updatePreferencesForNotifiable($notifiable, [
            'login' => ['channels' => ['database' => false]],
        ]);

        $this->assertFalse($this->preferenceFor($notifiable, 'login', 'database')?->enabled);
    }

    public function test_update_preferences_does_not_partially_apply_an_invalid_payload(): void
    {
        $notifiable = $this->createNotifiable();

        try {
            $this->service(['mail'])->updatePreferencesForNotifiable($notifiable, [
                'login' => ['channels' => ['mail' => false]],
                'backup_failed' => ['channels' => ['ntfy' => false]],
            ]);

            $this->fail('Expected an InvalidArgumentException.');
        } catch (InvalidArgumentException) {
            // Expected: the whole payload is rejected before anything is written.
        }

        $this->assertSame(0, $notifiable->notificationPreferences()->count());
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
        $catalog = $this->mock(NotificationCatalog::class);
        $catalog->allows('availableNotificationChannelsFor')->andReturn($availableChannels);
        $catalog->allows('requiredNotificationChannelsFor')->andReturn([]);
        $catalog->allows('notificationTypes')->andReturn($types ?? self::TYPES);

        return new NotificationPreferencesService($catalog);
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
