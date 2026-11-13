<?php

namespace SameOldNick\NotificationPreferences\Concerns;

use Illuminate\Notifications\AnonymousNotifiable;
use ReflectionAttribute;
use ReflectionClass;
use SameOldNick\NotificationPreferences\Attributes\NotificationType;
use SameOldNick\NotificationPreferences\Contracts\NotificationCatalog;
use SameOldNick\NotificationPreferences\Services\NotificationChannelResolver;

use function Illuminate\Support\enum_value;

trait RoutesViaNotifiablePreferences
{
    /**
     * Get the specific notification type for this notification.
     *
     * This is used to look up the user's preferences for this notification.
     * This should correspond to a key in the notification types configuration.
     * The type comes from the NotificationType attribute when present, and otherwise
     * from the class name. Overriding this method in the notification takes precedence.
     */
    public function notificationType(): string
    {
        return $this->getNotificationTypeFromAttribute() ?? $this->getNotificationTypeFromClassName();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via($notifiable = null): array
    {
        if ($notifiable === null) {
            return $this->getNullNotifiableVia();
        }

        return app(NotificationChannelResolver::class)->viaNotifiablePreferencesWithRequiredChannels(
            $notifiable,
            $this->notificationType(),
            $this->getAvailableChannels($notifiable),
            $this->getRequiredChannels($notifiable),
        );
    }

    /**
     * Get the channels to use when the notifiable is null. This is used for notifications sent to anonymous notifiables.
     *
     * @return array<int, string>
     */
    protected function getNullNotifiableVia(): array
    {
        return [];
    }

    /**
     * Get the available channels the notification can be sent to
     */
    protected function getAvailableChannels(mixed $notifiable): array
    {
        return app(NotificationCatalog::class)->availableNotificationChannelsFor($this->notificationType(), $notifiable);
    }

    /**
     * Get the required channels for a given notification type which must always be sent, regardless of user preferences.
     *
     * @return array<int, string>
     */
    public function getRequiredChannels(mixed $notifiable): array
    {
        return ! $notifiable instanceof AnonymousNotifiable ?
            app(NotificationCatalog::class)->requiredNotificationChannelsFor($this->notificationType(), $notifiable) :
            [];
    }

    /**
     * Get the notification type from the NotificationType attribute, if present.
     *
     * @return string|null The notification type, or null if not present.
     */
    protected function getNotificationTypeFromAttribute(): ?string
    {
        $reflection = new ReflectionClass($this);

        /** @var ReflectionAttribute<NotificationType>|null */
        $attribute = $reflection->getAttributes(NotificationType::class)[0] ?? null;

        return $attribute ? enum_value($attribute->newInstance()->type) : null;
    }

    /**
     * Get the notification type from the class name.
     *
     * @return string The notification type.
     */
    protected function getNotificationTypeFromClassName(): string
    {
        return get_class($this);
    }
}
