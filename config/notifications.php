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

        'password_change' => [
            'label' => 'Password Change',
            'category' => 'Security',
        ],

        'password_reset' => [
            'label' => 'Password Reset',
            'category' => 'Security',
        ],

        'password_changed' => [
            'label' => 'Password Changed',
            'category' => 'Security',
        ],

        '2fa_enabled' => [
            'label' => 'Two-Factor Authentication Enabled',
            'category' => 'Security',
        ],

        '2fa_disabled' => [
            'label' => 'Two-Factor Authentication Disabled',
            'category' => 'Security',
        ],

        'recovery_code_used' => [
            'label' => 'Recovery Code Used',
            'category' => 'Security',
        ],

        'recovery_code_generated' => [
            'label' => 'Recovery Code Generated',
            'category' => 'Security',
        ],

        'passkey_registered' => [
            'label' => 'Passkey Registered',
            'category' => 'Security',
        ],

        'passkey_deleted' => [
            'label' => 'Passkey Deleted',
            'category' => 'Security',
        ],

        'email_changed' => [
            'label' => 'Email Changed',
            'category' => 'Security',
        ],

        'account_deleted' => [
            'label' => 'Account Deleted',
            'category' => 'Security',
        ],
    ],
];
