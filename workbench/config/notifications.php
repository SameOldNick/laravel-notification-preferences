<?php

use SameOldNick\NotificationPreferences\Tests\Fixtures\TestNotification;

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

    'channels' => [
        'mail' => [
            'label' => 'Email',
        ],
        'ntfy' => [
            'label' => 'Ntfy',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Available Notification Channels
    |--------------------------------------------------------------------------
    |
    | This array defines the notification channels that are available for use
    | in the application. You can add or remove channels as needed. The available
    | channels will be used to send notifications to users based on their preferences.
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
    */
    'default_channels' => [
        'app',
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Types
    |--------------------------------------------------------------------------
    |
    | This array defines the different types of notifications that can be sent
    | to users. Each notification type has a label and a category. You can add
    | or remove notification types as needed. The notification types will be used
    | to look up user preferences and determine which channels to send notifications through.
    |
    */
    'types' => [
        'login' => [
            'label' => 'Login',
            'category' => 'Security',
        ],

        TestNotification::class => [
            'label' => 'Test Notification',
            'category' => 'Testing',
        ],
    ],
];
