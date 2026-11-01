<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Notifications Configuration
    |--------------------------------------------------------------------------
    |
    | This file is for storing the configuration settings related to notifications.
    | You can define default channels, notification types, and other settings
    | that will be used throughout the application.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Available Notification Channels
    |--------------------------------------------------------------------------
    |
    | This array defines the notification channels that are available for use
    | in the application. You can add or remove channels as needed. The available
    | channels will be used to send notifications to users based on their preferences.
    |
    | Note: The channels defined here should correspond to the channels specified
    | in the SameOldNick\NotificationPreferences\Enums\NotificationChannel enum.
    |
    */
    'available_channels' => [
        'mail',
        'ntfy',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Notification Channels
    |--------------------------------------------------------------------------
    |
    | This array defines the default notification channels that will be used
    | when sending notifications. These channels will be used if the user has not
    | specified their own preferences. You can modify this array to change the
    | default channels for notifications.
    |
    | Note: The channels defined here should correspond to the channels specified
    | in the SameOldNick\NotificationPreferences\Enums\NotificationChannel enum.
    |
    */
    'default_channels' => [
        'app',
    ],

];
