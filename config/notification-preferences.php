<?php

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
    | The enabled_by_default value for each channel determines whether the channel
    | is enabled when a notifiable has no stored preference for it. It is used
    | both for the preferences list and for delivery. If omitted, the channel is
    | enabled by default. Required channels always deliver regardless of defaults.
    |
    */

    'channels' => [
        'mail' => [
            'label' => 'Email',
            'enabled_by_default' => true,
        ],
        'ntfy' => [
            'label' => 'Ntfy',
            'enabled_by_default' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Available Notification Channels
    |--------------------------------------------------------------------------
    |
    | This optional array narrows the channels that notifiables can toggle to a
    | subset of the keys defined in `channels`. Leave it out to offer every
    | registered channel, or set it to an empty array to offer none. Every value
    | must be a key from `channels`; unknown channels fail loudly. Notification
    | types may narrow the list further with their own `available_channels`.
    |
    */
    // 'available_channels' => [
    //     'mail',
    //     'ntfy',
    // ],

    /*
    |--------------------------------------------------------------------------
    | Required Notification Channels
    |--------------------------------------------------------------------------
    |
    | This array defines the required notification channels that will be used
    | when sending notifications. These channels will be used no matter what the user's
    | preferences are. You can modify this array to change the default channels for notifications.
    |
    | Required channels are appended even when they are not registered in `channels`
    | or listed in `available_channels`, so they do not need a label.
    |
    */
    'required_channels' => [
        'database',
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Type Guard
    |--------------------------------------------------------------------------
    |
    | An optional class-string implementing
    | SameOldNick\NotificationPreferences\Contracts\NotificationTypeGuard. The guard
    | is consulted for every configured type, so it can hide types from notifiables
    | that may not use them. Leave it null to leave every type unguarded.
    |
    | The guard is resolved from the container, so it may declare constructor
    | dependencies. Set it to the contract itself to resolve whichever
    | implementation is bound to the container instead of naming a concrete class.
    |
    | Use a class-string rather than a closure so `config:cache` can still serialize
    | this file.
    |
    */
    'guard' => null,

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
    | A type may define `default_channels` to override the enabled_by_default
    | value of each channel for that type. A null or missing value falls back to
    | the channel defaults, while an empty array disables every channel by
    | default for that type.
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
