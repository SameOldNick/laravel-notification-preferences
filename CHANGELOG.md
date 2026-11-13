# Changelog

All notable changes are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.1] - 2026-11-12

### Added

- `RoutesViaNotifiablePreferences::getNullNotifiableVia()`, an overridable hook for the channels used when the notifiable is `null`. It returns no channels by default.

### Changed

- `NotificationChannelResolver` now requires a `NotificationTypeGuardResolver` in its constructor.

### Fixed

- Channel resolution did not apply the configured notification type guard, so a guarded-out type was still delivered when the application dispatched it. `NotificationChannelResolver` now checks the guard once per call and resolves no channels for a type it rejects.
- Required channels were appended even when the notifiable was not allowed the notification type, which delivered guarded-out types through `required_channels`.

## [1.1.0] - 2026-11-12

### Added

- Notification type guard configuration. A single class-string implementing `NotificationTypeGuard` is asked about every type, and notifiables it rejects no longer see or configure those types. The `guard` value may also be the `NotificationTypeGuard` contract, which resolves the implementation bound to the container.
- `NotificationCatalogForNotifiable` contract with `notificationTypesFor()`, implemented by the config-backed catalog.
- `NotificationPreferencesService::getNotificationTypesForNotifiable()` returning the guarded type list for a notifiable.
- `NotificationType` class attribute for mapping a notification to a preference type, accepting a string or a backed enum.
- `NotificationChannelDefaults` contract with `channelEnabledByDefault()`, implemented by the config-backed catalog.
- Per-channel `enabled_by_default` and per-type `default_channels` configuration, which control the state used when a notifiable has no stored preference.
- Boost guidance for upgrading an application to 1.1.0, covering configuration merging without force-publishing, the `available_channels` registration check, opt-in defaults, and the unchanged custom-catalog contract.

### Changed

- `NotificationPreferencesService::getPreferencesForNotifiable()` omits types that are guarded out for the notifiable.
- `NotificationPreferencesService::getPreferencesForNotifiable()` and `NotificationChannelResolver` now apply the configured channel defaults instead of always treating a missing preference row as enabled. Required channels are unaffected.
- The shipped configuration marks `ntfy` as opt-in (`enabled_by_default => false`), so existing notifiables without a stored preference for it stop receiving it. Set the flag to `true` to keep the previous behaviour.
- `updatePreferencesForNotifiable()` validates the whole payload and throws `InvalidArgumentException` for types or channels that are not available to the notifiable, before writing anything.
- `available_channels` is now optional. When it is not configured, every channel registered in `channels` is offered, so the channels map is the single source of truth; an explicit `[]` still offers none.
- The config-backed catalog throws `InvalidArgumentException` when `available_channels` (global or per type) names a channel that is not defined in `channels`. Required channels are exempt, since they are never offered to notifiables.
- The shipped configuration no longer declares `available_channels`, demonstrating the derived default.

### Upgrade Notes

See [Upgrading from 1.0.x to 1.1.0](README.md#upgrading) for the configuration, validation, and default-state changes required by this release.

## [1.0.0] - 2026-11-03

First release.

### Added

- Per-notifiable notification preferences stored by notification type and delivery channel.
- `HasNotificationPreferences` trait providing the `notificationPreferences()` morph-many relation.
- `RoutesViaNotifiablePreferences` trait that resolves a notification's `via()` channels from the notifiable's preferences.
- `NotificationCatalog` contract with a config-backed implementation, including per-type `available_channels` and `required_channels` overrides.
- `NotificationPreferencesService` for reading and updating a notifiable's preferences.
- `channels`, `available_channels`, `required_channels`, and `types` configuration with global fallbacks, empty-array opt-outs, and required channels that cannot be disabled.
- Publishable configuration and migration using the `notification-preferences-config` and `notification-preferences-migrations` tags.
- Translation support for notification type labels and categories.
- Support for Laravel 11, 12, and 13 on PHP 8.4+.
- Laravel Boost guidelines and the `notification-preferences-development` agent skill.

[1.1.1]: https://github.com/SameOldNick/laravel-notification-preferences/releases/tag/v1.1.1
[1.1.0]: https://github.com/SameOldNick/laravel-notification-preferences/releases/tag/v1.1.0
[1.0.0]: https://github.com/SameOldNick/laravel-notification-preferences/releases/tag/v1.0.0
