---
name: notification-preferences-development
description: 'ACTIVATE when the user works with per-type notification channel preferences in a Laravel app: configuring notification-preferences.channels, notification-preferences.available_channels, notification-preferences.required_channels, or notification-preferences.types; adding the HasNotificationPreferences or RoutesViaNotifiablePreferences traits; overriding notificationType(); building endpoints that read or update preferences with NotificationPreferencesService or NotificationCatalog; or upgrading this package (for example 1.0.x to 1.1.0) and reconciling its configuration. Activate when the user mentions notification preferences, opting out of channels, required channels, upgrading sameoldnick/laravel-notification-preferences, or this package. Do NOT activate for general Laravel notification sending, mail configuration, or queueing that does not involve per-type channel preferences.'
license: MIT
metadata:
  author: SameOldNick
---

# Laravel Notification Preferences Development

## Overview

This package stores per-notifiable, per-type, per-channel preferences in the `notifiable_notification_preferences` table and resolves a notification's `via()` channels from them.

- Available channels are offered to the user and can be disabled.
- Required channels are always appended and cannot be disabled.
- Channels without a stored preference are enabled, so new channels reach existing users.

## When to use this skill

Use it when adding, configuring, or debugging notification preferences, when building the UI/API that reads and updates them, or when upgrading the package and reconciling its configuration.

## Package map

| Path | Purpose |
| --- | --- |
| `config/notification-preferences.php` | Channel, available channel, required channel, and notification type configuration |
| `src/Concerns/HasNotificationPreferences.php` | Adds the `notificationPreferences()` morph-many relation to a notifiable |
| `src/Concerns/RoutesViaNotifiablePreferences.php` | Adds preference-aware `via()` and `notificationType()` to a notification |
| `src/Contracts/NotificationCatalog.php` | Read-only catalog of types, channels, and per-type channel lists |
| `src/Contracts/NotificationCatalogForNotifiable.php` | Optional extension for catalogs that resolve types per notifiable |
| `src/Contracts/NotificationChannelDefaults.php` | Optional extension for catalogs that provide per-channel default states |
| `src/Contracts/NotificationTypeGuard.php` | Contract for the catalog-wide guard that hides types from some notifiables |
| `src/Providers/NotificationCatalogConfigProvider.php` | Config-backed catalog implementation with per-type fallback rules |
| `src/Services/NotificationChannelResolver.php` | Resolves the final channel list for a notifiable and type |
| `src/Services/NotificationPreferencesService.php` | Reads and writes preferences for a notifiable |
| `src/Models/NotifiableNotificationPreference.php` | Eloquent model for a single preference row |

## Setup workflow

- [ ] Require `sameoldnick/laravel-notification-preferences`.
- [ ] Publish the config and migration with `php artisan vendor:publish --tag=notification-preferences-config` and `php artisan vendor:publish --tag=notification-preferences-migrations`, then run `php artisan migrate`.
- [ ] Add `HasNotificationPreferences` to the notifiable model (usually `User`).
- [ ] Add each channel to `notification-preferences.channels`, then narrow `notification-preferences.available_channels` only if some registered channels should not be offered.
- [ ] Add each notification type to `notification-preferences.types`.
- [ ] Add `RoutesViaNotifiablePreferences` to each notification and override `notificationType()` when the default FQCN key is not desired.

## Channel configuration

```php
// config/notification-preferences.php
return [
    'channels' => [
        'mail' => ['label' => 'Email'],
        'database' => ['label' => 'In-app'],
    ],

    'available_channels' => ['mail', 'database'],

    'required_channels' => ['database'],
];
```

- `channels` supplies labels for display plus an optional `enabled_by_default` flag that sets the state when no preference row exists.
- `available_channels` and `required_channels` drive behavior and must contain channel names Laravel can resolve: `mail`, `database`, `broadcast`, `vonage`, or a channel class name.
- `available_channels` is optional: omit it to offer every channel in `channels`, or set `[]` to offer none. Every value must be a key of `channels`; an unregistered channel throws when the catalog resolves it. Required channels are exempt from that check because they are never offered.
- Labels may be plain strings or translation keys.
- A channel with no stored preference is delivered only when it is enabled by default. Required channels are always appended, regardless of defaults.
- Required channels are appended even when they are not in `available_channels`. Only mark a channel required when the application can deliver through it.

## Adding a notification type

```php
'types' => [
    'security-alert' => [
        'label' => 'Security Alert',
        'category' => 'Security',
    ],
],
```

- The key must match the notification's `notificationType()` return value.
- `label` and `category` are passed through `__()`. Missing translations fall back to the configured string.
- A notification type that is not configured has no preference entries and cannot be managed by users. It still sends on the global available channels because the per-type lookup falls back to them.

## Per-type channel overrides

```php
'types' => [
    'security-alert' => [
        'label' => 'Security Alert',
        'category' => 'Security',
        'available_channels' => ['mail', 'database'],
        'required_channels' => ['mail'],
        'default_channels' => ['mail'],
    ],

    'marketing' => [
        'label' => 'Marketing',
        'category' => 'General',
        'required_channels' => [],
        'default_channels' => [],
    ],
],
```

- Missing or `null` falls back to the matching global list, or to the channel `enabled_by_default` values for `default_channels`.
- `[]` opts out of the global list; `'required_channels' => []` forces nothing for that type, and `'default_channels' => []` disables every channel by default for that type.
- Overrides apply only to the type that declares them.
- A type with no available channels produces no preference entries.

## Guarding types

Some types only apply to certain notifiables. Configure one guard for the whole catalog; it is asked about every configured type:

```php
'guard' => App\Notifications\NotificationGuard::class,
```

```php
<?php

namespace App\Notifications;

use SameOldNick\NotificationPreferences\Contracts\NotificationTypeGuard;

class NotificationGuard implements NotificationTypeGuard
{
    public function allows(string $notificationType, object $notifiable): bool
    {
        return match ($notificationType) {
            'billing-failed' => $notifiable->hasRole('billing'),
            default => true,
        };
    }
}
```

- The guard is resolved from the container, so it may declare constructor dependencies.
- Set `guard` to `NotificationTypeGuard::class` to resolve the implementation bound to the contract. Without a binding, configuring the contract throws `InvalidArgumentException`.
- A missing or `null` guard leaves every type unguarded. A guard that is not a class-string implementing the contract throws `InvalidArgumentException`.
- Use a class-string rather than a closure so `config:cache` still works.
- Guarded-out types are omitted from `getPreferencesForNotifiable()` and `getNotificationTypesForNotifiable()`, and rejected by `updatePreferencesForNotifiable()`.
- The guard runs per type and notifiable whenever preferences are listed and whenever channels are resolved, so keep it cheap.
- Guarded-out types are not delivered either: `via()` resolves no channels, required ones included. Because delivery can run in a queue worker, the guard must not depend on request-only state; use the notifiable, for example with `Gate::forUser($notifiable)`.

## Notification wiring

```php
<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use SameOldNick\NotificationPreferences\Attributes\NotificationType;
use SameOldNick\NotificationPreferences\Concerns\RoutesViaNotifiablePreferences;

#[NotificationType('security-alert')]
class SecurityAlert extends Notification
{
    use RoutesViaNotifiablePreferences;

    // ...
}
```

- `notificationType()` defaults to the notification's fully-qualified class name. Apply the `NotificationType` attribute, or override `notificationType()`, to use a shorter key from `notification-preferences.types`. The method takes precedence over the attribute, and attributes are not inherited.
- Do not also define `via()`. A method on the class takes precedence over the trait and bypasses preference resolution.
- Anonymous notifiables receive the available channels but never the required channels.

## Channel resolution

`RoutesViaNotifiablePreferences::via()` calls `NotificationChannelResolver::viaNotifiablePreferencesWithRequiredChannels()`, which:

1. Starts with the available channels for the type: its own `available_channels`, otherwise the global list, otherwise every key in `channels`.
2. Removes channels with an explicit disabled preference row, and channels that are disabled by default and have no stored row.
3. Appends the required channels for the type.
4. Deduplicates while preserving order.

Defaults come from `channels.{channel}.enabled_by_default`, which the type can override with `default_channels`. The catalog is asked through the optional `NotificationChannelDefaults` contract; catalogs that do not implement it treat missing rows as enabled.

If the notifiable is `null`, the trait returns no channels at all. If the notifiable has no `notificationPreferences()` method or the type has no available channels, the available channels are returned unchanged, and required channels are still appended when the notifiable supports preferences. Defaults never apply to required channels.

## Reading preferences

```php
use SameOldNick\NotificationPreferences\Services\NotificationPreferencesService;

$preferences = app(NotificationPreferencesService::class)
    ->getPreferencesForNotifiable($request->user());
```

The result is keyed by `"{channel}:{type}"` and contains one entry per configured type/available-channel pair:

```php
[
    'mail:security-alert' => [
        'enabled' => true,
        'key' => 'security-alert',
        'label' => 'Security Alert',
        'category' => 'Security',
        'channel' => 'mail',
    ],
]
```

Required channels that are not in the type's available channels are not returned, since users cannot toggle them.

Type labels and categories are translated by the service. Channel labels are not included; get them from `NotificationCatalog::notificationChannels()` and translate them with `__()` when rendering.

## Updating preferences

```php
use SameOldNick\NotificationPreferences\Services\NotificationPreferencesService;

app(NotificationPreferencesService::class)->updatePreferencesForNotifiable($request->user(), [
    'security-alert' => [
        'channels' => [
            'mail' => false,
            'database' => true,
        ],
    ],
]);
```

- The payload shape is `[type => ['channels' => [channel => bool]]]`.
- Existing rows are updated, missing rows are created, and unchanged rows are skipped.
- The service rejects types that are not available to the notifiable and channels that are not available (or required) for the type, throwing `InvalidArgumentException` before writing anything.
- Both methods throw `InvalidArgumentException` when the model does not use `HasNotificationPreferences`.
- Preference rows for channels later removed from `available_channels` are ignored; they do not need to be deleted.

## Validating input

Validate against the catalog so users cannot submit types or channels the application does not support. `getNotificationTypesForNotifiable()` applies guards, so guarded-out types are rejected too:

```php
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use SameOldNick\NotificationPreferences\Contracts\NotificationCatalog;
use SameOldNick\NotificationPreferences\Services\NotificationPreferencesService;

public function update(Request $request)
{
    $catalog = app(NotificationCatalog::class);
    $service = app(NotificationPreferencesService::class);
    $notifiable = $request->user();

    $notificationTypes = $service->getNotificationTypesForNotifiable($notifiable);

    $validated = $request->validate([
        'types' => [
            'required',
            'array:'.implode(',', array_keys($notificationTypes)),
        ],
        'types.*.channels' => Rule::forEach(function (mixed $channels, string $attribute) use ($catalog, $notifiable) {
            $type = Str::between($attribute, 'types.', '.channels');

            return [
                'required',
                'array:'.implode(',', $catalog->availableNotificationChannelsFor($type, $notifiable)),
            ];
        }),
        'types.*.channels.*' => ['required', 'boolean'],
    ]);

    $service->updatePreferencesForNotifiable($notifiable, $validated['types']);

    return response()->noContent();
}
```

The package does not register routes, controllers, or views. Authentication, authorization, and the request shape are the application's responsibility.

## Custom catalog implementations

`NotificationCatalog` is a singleton bound to `NotificationCatalogConfigProvider`. Rebinding it changes both routing (`RoutesViaNotifiablePreferences`) and preference reads/writes (`NotificationPreferencesService`), because both resolve the interface from the container.

```php
$this->app->singleton(NotificationCatalog::class, DatabaseNotificationCatalog::class);
```

- Implement all six methods: `notificationTypes()`, `notificationChannels()`, `availableNotificationChannels()`, `requiredNotificationChannels()`, `availableNotificationChannelsFor()`, and `requiredNotificationChannelsFor()`.
- Also implement `NotificationCatalogForNotifiable` to hide whole types per notifiable through `notificationTypesFor()`. Catalogs that only implement `NotificationCatalog` are treated as unguarded.
- Return the documented metadata shapes: types expose `label` and `category` plus optional channel lists; channels expose `label`.
- Return `list<string>` channel keys. The resolver deduplicates and preserves order.
- The per-notifiable methods receive the notifiable, so availability can depend on a plan, tenant, or other user state. Guard channels by filtering `availableNotificationChannelsFor()`; required channels are appended even when unavailable.
- The catalog is a singleton. Do not store notifiable state on the instance; compute per call or memoize keyed by the notifiable.
- `getPreferencesForNotifiable()` only uses the type list and `availableNotificationChannelsFor()`. Required channels affect delivery, not the returned preference list.

## Upgrading an application

### From 1.1.0 to 1.1.1

This release applies the notification type guard when channels are resolved and adds a hook for `null` notifiables. No database or configuration changes are required. Work through these checks before changing code:

1. Audit every guard for delivery. `via()` now resolves no channels for a type the guard rejects, and required channels no longer override the guard. A guard written only for the preferences UI now also runs when a notification is sent, including from queue workers, so it must not rely on request-only state such as the session or the authenticated guard. Use the notifiable it receives, for example with `Gate::forUser($notifiable)`.
2. Resolve `NotificationChannelResolver` from the container. Its constructor now also requires a `NotificationTypeGuardResolver`, so code that instantiates it directly must pass the guard resolver as well.
3. Override `getNullNotifiableVia()` only if notifications sent without a notifiable should use a fixed channel list; it returns no channels by default.

### From 1.0.x to 1.1.0

Upgrading from 1.0.x to 1.1.0 needs no database changes. Work through these checks before changing code:

1. Confirm the installed version with `composer show sameoldnick/laravel-notification-preferences`.
2. Never run `vendor:publish --force` for `notification-preferences-config`. It replaces the application's customized `channels` and `types`, and the 1.1.0 file ships `ntfy` as opt-in (`enabled_by_default => false`). Merge new keys by hand instead.
3. Add the new keys only where the application needs them: `channels.*.enabled_by_default`, `types.*.default_channels`, and `guard`. Omitting them keeps the previous behaviour; `guard` is `null` by default.
4. Check that every value of `available_channels` (global and per type) is a key of `channels`. Unregistered channels now throw `InvalidArgumentException` when the catalog resolves that list. Add the channel to `channels` with a `label`, remove it from `available_channels`, or delete `available_channels` entirely to offer every registered channel.
5. Make sure callers of `updatePreferencesForNotifiable()` validate the payload first or catch `InvalidArgumentException`, which is now thrown for unavailable types and channels before anything is written.
6. Leave custom `NotificationCatalog` bindings alone: the interface is unchanged. Implement `NotificationCatalogForNotifiable`, `NotificationChannelDefaults`, or `NotificationTypeGuard` only to opt in.
7. Run the application's tests. A channel marked opt-in no longer reaches notifiables without a stored preference, so preference and delivery tests are the ones most likely to need attention.

Required channels are unaffected by steps 3 and 4, because they are never offered to notifiables.

## Testing

- Package tests: `composer test`, `composer lint`, `composer format`.
- Application tests: override configuration per test with `config(['notification-preferences.available_channels' => ['mail', 'database']])` and use `RefreshDatabase`.
- Create preference rows through the relation: `$user->notificationPreferences()->create(['notification_type' => 'security-alert', 'channel' => 'mail', 'enabled' => false])`.
