<?php

use SameOldNick\NotificationPreferences\Tests\Fixtures\TestNotification;

return [
    /*
    |--------------------------------------------------------------------------
    | Notification Preferences Configuration
    |--------------------------------------------------------------------------
    |
    | This file is for storing the configuration settings related to notification
    | preferences. You can define channels, notification types, and other
    | settings that will be used throughout the application.
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
    | Required Notification Channels
    |--------------------------------------------------------------------------
    |
    | This array defines the required notification channels that will be used
    | when sending notifications. These channels will be used no matter what the user's
    | preferences are. You can modify this array to change the default channels for notifications.
    |
    */
    'required_channels' => [
        'database',
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
    | The label and category values are used for display purposes and can be translation keys.
    | The label is the name of the notification type, and the category is used to group related
    | notification types together.
    |
    | A type may also define its own `available_channels` and `required_channels`
    | lists. A null or missing value falls back to the global lists defined above,
    | while an empty array opts the type out of them entirely.
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
