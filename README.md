# Laravel Notification Preferences

[![codecov](https://codecov.io/gh/SameOldNick/laravel-notification-preferences/graph/badge.svg?token=eL1FDSZ9T8)](https://codecov.io/gh/SameOldNick/laravel-notification-preferences)
[![Tests](https://github.com/SameOldNick/laravel-notification-preferences/actions/workflows/tests.yml/badge.svg)](https://github.com/SameOldNick/laravel-notification-preferences/actions/workflows/tests.yml)
![Packagist Downloads](https://img.shields.io/packagist/dt/SameOldNick/laravel-notification-preferences)

Let users choose which channels each type of notification is delivered through, while guaranteeing that important notifications always reach them.

## Requirements

- PHP 8.4 or higher
- Laravel 11, 12, or 13
- Composer

## Installation

Install the package with Composer:

```bash
composer require sameoldnick/laravel-notification-preferences
```

Publish the configuration file and the migration:

```bash
php artisan vendor:publish --tag=notification-preferences-config
php artisan vendor:publish --tag=notification-preferences-migrations
```

Run the migration:

```bash
php artisan migrate
```

Add the `HasNotificationPreferences` trait to the notifiable model, usually your `User` model:

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use SameOldNick\NotificationPreferences\Concerns\HasNotificationPreferences;

class User extends Authenticatable
{
    use HasNotificationPreferences;

    // ...
}
```

## Configuration

The published configuration file is `config/notification-preferences.php`.

### Channels

Three top-level keys configure channels:

- `channels` — the channels that can be offered, each with a label and an optional `enabled_by_default` flag.
- `available_channels` — the channels offered to users. Users can enable or disable each one.
- `required_channels` — the channels that are always sent, regardless of user preferences. Users cannot disable these.

```php
<?php

// config/notification-preferences.php

return [
    'channels' => [
        'mail' => [
            'label' => 'Email',
        ],
        'database' => [
            'label' => 'In-app',
        ],
        'ntfy' => [
            'label' => 'Ntfy',
            'enabled_by_default' => false,
        ],
    ],

    'available_channels' => [
        'mail',
        'database',
        'ntfy',
    ],

    'required_channels' => [
        'database',
    ],
];
```

- Channel keys must match the channel names your notifications return from `via()`, such as `mail`, `database`, `broadcast`, or a channel class name.
- Labels may be plain strings or translation keys.
- `enabled_by_default` sets the state used when the notifiable has no stored preference for that channel. It affects both the preferences list and delivery, and an omitted value means enabled. Required channels ignore it.
- A required channel does not have to appear in `available_channels`; it is appended to the resolved channels either way.
- The published file contains example values. Replace them with the channels your application actually sends through, and only list required channels your application can deliver.

> **Note:** `channels` provides display metadata (used by `NotificationCatalog::notificationChannels()`) and the per-channel default state. `available_channels` and `required_channels` control which channels are offered and forced.

### Notification types

Each notification type is keyed by the value returned from its `notificationType()` method (or from its `NotificationType` attribute) and defines a label and a category. Both may be translation keys.

```php
'types' => [
    'my-notification' => [
        'label' => 'My Notification',
        'category' => 'General',
    ],
],
```

### Guarding notification types

Some types only apply to certain notifiables, based on roles, permissions, or a plan. Configure a single guard for the whole catalog; it is asked about every configured type:

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
            'billing_failed' => $notifiable->hasRole('billing'),
            default => true,
        };
    }
}
```

- The guard is resolved from the container, so it may declare constructor dependencies. Bind it as a singleton if constructing it is expensive.
- Set `guard` to `NotificationTypeGuard::class` to resolve the implementation bound to the contract instead of naming a concrete class. Configuring the contract without a binding throws an `InvalidArgumentException`.
- A missing or `null` guard leaves every type unguarded. Any other value that is not a class-string implementing the contract throws an `InvalidArgumentException`.
- Use a class-string rather than a closure, so `config:cache` can still serialize the configuration.
- Guarded-out types are omitted from `getPreferencesForNotifiable()` and `getNotificationTypesForNotifiable()`, and rejected by `updatePreferencesForNotifiable()`.
- The guard runs once per type and notifiable on every call, so keep it cheap.
- Guards control what a notifiable can see and configure. They do not prevent the application from sending that notification, so choose recipients with your own authorization.

To use a Gate or policy, call it from the guard:

```php
public function allows(string $notificationType, object $notifiable): bool
{
    return Gate::forUser($notifiable)->allows('receive-notification-type', $notificationType);
}
```

### Per-type channel overrides

A notification type may override the global channel lists:

```php
'types' => [
    'security-alert' => [
        'label' => 'Security Alert',
        'category' => 'Security',
        'available_channels' => ['mail', 'database'],
        'required_channels' => ['mail'],
        'default_channels' => ['mail'],
    ],
],
```

- Omitting a key or setting it to `null` falls back to the matching global list.
- An empty array opts out of the global list. For example, `'required_channels' => []` means no channel is forced for that type.
- `default_channels` overrides the channel defaults for that type: omitted or `null` falls back to each channel's `enabled_by_default`, while `[]` disables every channel by default for the type.
- Overrides only apply to the type that declares them.
- A type with no available channels (global or overridden) produces no preference entries.

### How the channel list is resolved

When a notification that uses `RoutesViaNotifiablePreferences` is sent, its channels are resolved in this order:

1. Start with the available channels for the notification type.
2. Remove channels the notifiable has disabled, and channels that are disabled by default and have no stored preference.
3. Append the required channels for the notification type.
4. Remove duplicates.

A channel with no stored preference follows its default state: enabled unless `enabled_by_default` (or the type's `default_channels`) turns it off. Newly added channels therefore still reach existing users unless they are opt-in. Saving a preference writes an explicit row for the submitted channels, which then stops following later default changes. Required channels are appended either way.

Guards do not run during delivery: a guarded-out type still resolves channels if the application sends it. Guard recipients in the application instead.

## Usage

Add the `RoutesViaNotifiablePreferences` trait to every notification that should respect preferences:

```php
<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use SameOldNick\NotificationPreferences\Concerns\RoutesViaNotifiablePreferences;

class MyNotification extends Notification
{
    use RoutesViaNotifiablePreferences;

    // ...
}
```

The trait provides `via()`. Do not also define `via()` on the notification, or it will take precedence and bypass preference routing.

### Mapping a notification to a type

By default, `notificationType()` returns the notification's fully-qualified class name, which must exist as a key in `notification-preferences.types`. To use a shorter key instead, apply the `NotificationType` attribute to the class:

```php
<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use SameOldNick\NotificationPreferences\Attributes\NotificationType;
use SameOldNick\NotificationPreferences\Concerns\RoutesViaNotifiablePreferences;

#[NotificationType('my-notification')]
class MyNotification extends Notification
{
    use RoutesViaNotifiablePreferences;

    public function toArray($notifiable): array
    {
        return ['message' => 'Hello!'];
    }
}
```

The `my-notification` key matches the type defined in the [Notification types](#notification-types) section, so the notifiable's preferences for that type decide which channels this notification uses.

The attribute also accepts a backed enum, so one enum can own your type keys:

```php
// app/Enums/NotificationType.php
enum NotificationType: string
{
    case Login = 'login';
    case BackupFailed = 'backup_failed';
}
```

```php
<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use Illuminate\Notifications\Notification;
use SameOldNick\NotificationPreferences\Attributes\NotificationType as NotificationTypeAttribute;
use SameOldNick\NotificationPreferences\Concerns\RoutesViaNotifiablePreferences;

#[NotificationTypeAttribute(NotificationType::Login)]
class LoginNotification extends Notification
{
    use RoutesViaNotifiablePreferences;
}
```

The enum case resolves to its backing value, so `NotificationType::Login` maps to the `login` type. Alias one of the two imports because both classes are named `NotificationType`.

The resolved value must match a key in `notification-preferences.types`.

Resolution order is the notification's own `notificationType()` method, then the attribute, then the class name, so overriding the method still wins. Attributes are not inherited, so place it on the concrete notification class rather than a base class.

### Reading and updating preferences

The package does not ship routes or controllers. Resolve `NotificationPreferencesService` from the container and expose it through your own application:

```php
use SameOldNick\NotificationPreferences\Services\NotificationPreferencesService;

$service = app(NotificationPreferencesService::class);

// Keyed by "{channel}:{type}", for example "mail:my-notification".
$preferences = $service->getPreferencesForNotifiable($user);

$service->updatePreferencesForNotifiable($user, [
    'my-notification' => [
        'channels' => [
            'mail' => false,
            'database' => true,
        ],
    ],
]);
```

`getPreferencesForNotifiable()` returns type labels and categories, but only channel keys. To display channel names (for example, as column headings), resolve the catalog and call `notificationChannels()`:

```php
use SameOldNick\NotificationPreferences\Contracts\NotificationCatalog;

$channels = app(NotificationCatalog::class)->notificationChannels();

// [
//     'mail' => ['label' => 'Email'],
//     'database' => ['label' => 'In-app'],
// ]
```

Channel labels may be translation keys, so pass them through `__()` before displaying them.

`updatePreferencesForNotifiable()` rejects types that are not available to the notifiable and channels that are not available for the submitted type, throwing an `InvalidArgumentException` before writing anything. Validate the request first so users get a 422 instead of a 500:

```php
use SameOldNick\NotificationPreferences\Contracts\NotificationCatalog;
use SameOldNick\NotificationPreferences\Services\NotificationPreferencesService;

$catalog = app(NotificationCatalog::class);
$service = app(NotificationPreferencesService::class);

$allowedTypes = array_keys($service->getNotificationTypesForNotifiable($user));
```

`getNotificationTypesForNotifiable()` applies the same guards as `getPreferencesForNotifiable()`, so guarded-out types cannot be submitted either. A channel that is required for a type but not available is still accepted, because the type is deliverable through it.

### Custom notification catalogs

`NotificationCatalog` is bound to the config-backed `NotificationCatalogConfigProvider`. To source types or channels from somewhere else — a database, a tenant, or a user's plan — bind your own implementation in a service provider:

```php
<?php

namespace App\Providers;

use App\Support\DatabaseNotificationCatalog;
use Illuminate\Support\ServiceProvider;
use SameOldNick\NotificationPreferences\Contracts\NotificationCatalog;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NotificationCatalog::class, DatabaseNotificationCatalog::class);
    }
}
```

Both `RoutesViaNotifiablePreferences` and `NotificationPreferencesService` resolve the interface from the container, so replacing the binding changes routing and preferences together. Implement all six interface methods, return `list<string>` channel keys, and keep the singleton free of per-notifiable state — `availableNotificationChannelsFor()` and `requiredNotificationChannelsFor()` receive the notifiable for that reason.

To hide whole types from specific notifiables, also implement `NotificationCatalogForNotifiable`. It adds `notificationTypesFor(object $notifiable): array`, and catalogs that implement only `NotificationCatalog` are treated as unguarded. The config-backed provider implements it by running each type's `guard`.

To control the state used when a notifiable has no stored preference, implement `NotificationChannelDefaults`. It adds `channelEnabledByDefault(string $notificationType, string $channel, object $notifiable): bool`, which both the preferences list and delivery consult. Catalogs that do not implement it treat missing rows as enabled, and the config-backed provider reads `enabled_by_default` and `default_channels`.

Channels are already scoped to the notifiable through `availableNotificationChannelsFor()`. If you extend the config-backed provider, filter the parent list there:

```php
public function availableNotificationChannelsFor(string $notificationType, object $notifiable): array
{
    $channels = parent::availableNotificationChannelsFor($notificationType, $notifiable);

    return array_values(array_filter(
        $channels,
        fn (string $channel): bool => $channel !== 'sms' || $notifiable->phone_verified_at !== null,
    ));
}
```

Required channels are appended even when they are not available, so guard them too if they must never be delivered.

## Laravel Boost

This package ships AI guidelines and an on-demand agent skill for [Laravel Boost](https://laravel.com/docs/boost):

- Guidelines: `resources/boost/guidelines/core.blade.php`
- Skill: `resources/boost/skills/notification-preferences-development/SKILL.md`

Both are installed automatically when an application runs `php artisan boost:install` (skills depend on the user selecting the skills feature).

## Development

```bash
composer install
composer test
composer lint
composer format
```

## Security

Please review [SECURITY.md](SECURITY.md) for the vulnerability reporting policy.

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).
