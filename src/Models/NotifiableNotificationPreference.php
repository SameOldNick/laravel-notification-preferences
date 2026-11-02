<?php

namespace SameOldNick\NotificationPreferences\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Class NotifiableNotificationPreference
 *
 * Represents a user's notification preference for a specific notification type and channel.
 *
 * @property string $notifiable_type The type of the notifiable entity (e.g., User).
 * @property int $notifiable_id The ID of the notifiable entity.
 * @property string $notification_type The type of notification (e.g., email, SMS).
 * @property string $channel The channel through which the notification is sent (e.g., mail, ntfy).
 * @property bool $enabled Indicates whether the notification preference is enabled or disabled.
 */
#[Fillable([
    'notifiable_type',
    'notifiable_id',
    'notification_type',
    'channel',
    'enabled',
])]
class NotifiableNotificationPreference extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    /**
     * Get the notifiable entity that owns this preference.
     */
    public function notifiable()
    {
        return $this->morphTo();
    }
}
