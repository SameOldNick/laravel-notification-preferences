# Changelog

All notable changes are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

[1.0.0]: https://github.com/SameOldNick/laravel-notification-preferences/releases/tag/v1.0.0
