## Laravel Notification Preferences

This package lets a notifiable (usually the `User` model) enable or disable each delivery channel per notification type. Required channels are always delivered, even when a user has disabled them.

### Setup

- Install with `composer require sameoldnick/laravel-notification-preferences`.
- Publish the configuration and migration with `php artisan vendor:publish --tag=notification-preferences-config` and `php artisan vendor:publish --tag=notification-preferences-migrations`, then run `php artisan migrate`.
- Add `SameOldNick\NotificationPreferences\Concerns\HasNotificationPreferences` to the notifiable model.
- Add `SameOldNick\NotificationPreferences\Concerns\RoutesViaNotifiablePreferences` to every notification that should respect preferences.

### Configuration

Configuration lives in `config/notification-preferences.php` and is read through the `notification-preferences.*` keys.

- `channels` maps a channel key to `['label' => ...]` and an optional `enabled_by_default` boolean. Keys must match the channel names returned from `via()`, such as `mail`, `database`, `broadcast`, or a channel class name.
- `available_channels` lists the channels users can toggle. Each one starts in its `enabled_by_default` state, which is enabled when unset, until the user changes it.
- `required_channels` lists channels that are appended to every notification of a type, even when disabled. They do not need to appear in `available_channels`, but they must be channels the application can deliver through. Defaults never apply to them.
- `types` is keyed by the value returned from `notificationType()`. Each type requires `label` and `category`, both of which may be translation keys.
- A type may override `available_channels`, `required_channels`, and `default_channels`. A missing or `null` value falls back to the global list or the channel defaults, while `[]` opts out entirely.
- Configure a single `guard` class-string implementing `NotificationTypeGuard`. It receives each type in `allows(string $notificationType, object $notifiable)`, and notifiables it rejects never see or configure that type. Guarded-out types are also rejected by `updatePreferencesForNotifiable()`. Set `guard` to `NotificationTypeGuard::class` to resolve the container binding instead. Use a class-string rather than a closure so `config:cache` keeps working.

@verbatim
<code-snippet name="Notification type configuration" lang="php">
'types' => [
    'security-alert' => [
        'label' => 'Security Alert',
        'category' => 'Security',
        'available_channels' => ['mail', 'database'],
        'required_channels' => ['mail'],
    ],
],
</code-snippet>
@endverbatim

### Conventions

- `notificationType()` defaults to the notification's fully-qualified class name. Apply the `NotificationType` attribute (a string or backed enum) or override `notificationType()` to use a key that exists in `notification-preferences.types`; otherwise users cannot manage that notification. An explicit method takes precedence over the attribute, and attributes are not inherited.
- Do not also define `via()` on a notification that uses `RoutesViaNotifiablePreferences`. The class method takes precedence and bypasses preference routing.
- A channel with no stored preference follows its default state: enabled unless `enabled_by_default` (or the type's `default_channels`) turns it off. Required channels are unaffected, and saving a preference stores an explicit row that stops following later default changes.
- Use `SameOldNick\NotificationPreferences\Contracts\NotificationCatalog` to list notification types and channels, and to resolve the channels available to a specific type.
- Use `NotificationPreferencesService::getNotificationTypesForNotifiable()` (backed by `NotificationCatalogForNotifiable`) to list the types a specific notifiable may configure; guards are applied there.
- `NotificationCatalog` is bound to `NotificationCatalogConfigProvider`. Bind a custom implementation when types or channels come from a database, tenant, or plan; routing and preference services both resolve the interface, so one binding changes both. Keep the singleton stateless.
- Use `SameOldNick\NotificationPreferences\Services\NotificationPreferencesService` to read and write preferences. The package ships no routes, controllers, or views, so the application must expose and validate its own endpoints.
- Validate incoming types and channels before updating. The service rejects unavailable types and channels with `InvalidArgumentException`, and `getNotificationTypesForNotifiable()` returns the guarded type list to validate against.

For detailed workflows, use the `notification-preferences-development` Boost skill.
