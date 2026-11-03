# Laravel Notification Preferences

[![codecov](https://codecov.io/gh/SameOldNick/laravel-notification-preferences/graph/badge.svg?token=eL1FDSZ9T8)](https://codecov.io/gh/SameOldNick/laravel-notification-preferences)
[![Tests](https://github.com/SameOldNick/laravel-notification-preferences/actions/workflows/tests.yml/badge.svg)](https://github.com/SameOldNick/laravel-notification-preferences/actions/workflows/tests.yml)

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

The published configuration file is `config/notifications.php`.

### Channels

Three top-level keys configure channels:

- `channels` — the channels that can be offered, each with a label.
- `available_channels` — the channels offered to users by default. Users can enable or disable each one.
- `required_channels` — the channels that are always sent by default, regardless of user preferences. Users cannot disable these.

```php
<?php

// config/notifications.php

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
- A required channel does not have to appear in `available_channels`; it is appended to the resolved channels either way.
- The published file contains example values. Replace them with the channels your application actually sends through, and only list required channels your application can deliver.

> **Note:** `channels` provides display metadata (used by `NotificationCatalog::notificationChannels()`). `available_channels` and `required_channels` control delivery behavior.

### Notification types

Each notification type is keyed by the value returned from its `notificationType()` method and defines a label and a category. Both may be translation keys.

```php
'types' => [
    'my-notification' => [
        'label' => 'My Notification',
        'category' => 'General',
    ],
],
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
    ],
],
```

- Omitting a key or setting it to `null` falls back to the matching global list.
- An empty array opts out of the global list. For example, `'required_channels' => []` means no channel is forced for that type.
- Overrides only apply to the type that declares them.
- A type with no available channels (global or overridden) produces no preference entries.

### How the channel list is resolved

When a notification that uses `RoutesViaNotifiablePreferences` is sent, its channels are resolved in this order:

1. Start with the available channels for the notification type.
2. Remove channels the notifiable has explicitly disabled.
3. Append the required channels for the notification type.
4. Remove duplicates.

Channels with no stored preference are treated as enabled, so newly added channels still reach existing users.

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

By default, `notificationType()` returns the notification's fully-qualified class name, which must exist as a key in `notifications.types`. To use a shorter key instead, override the method:

```php
public function notificationType(): string
{
    return 'my-notification';
}
```

The returned value must match a key in `notifications.types`.

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

Validate the incoming types and channels against `SameOldNick\NotificationPreferences\Contracts\NotificationCatalog` before calling `updatePreferencesForNotifiable()`, since the service stores whatever it is given.

## Laravel Boost

This package ships Boost AI guidelines in `resources/boost/guidelines/core.blade.php`. They are installed automatically when an application runs `php artisan boost:install`.

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
