<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use SameOldNick\NotificationPreferences\Contracts\NotificationChannelProvider;
use SameOldNick\NotificationPreferences\Contracts\NotificationTypeProvider;
use SameOldNick\NotificationPreferences\Services\NotificationPreferencesService;

Route::get('/', function () {
    return view('welcome');
});

Route::group(['middleware' => ['auth']], function () {
    Route::get('/notification-preferences', function (Request $request) {
        $preferences = app(NotificationPreferencesService::class)
            ->getPreferencesForNotifiable($request->user());

        return response()->json($preferences);
    })->name('notification-preferences.index');

    Route::post('/notification-preferences', function (Request $request) {
        $notifiable = $request->user();
        $notificationChannels = app(NotificationChannelProvider::class);

        $rules = [
            'types' => [
                'required',
                'array',
                Rule::arrayKeys(array_keys(app(NotificationTypeProvider::class)->notificationTypes())),
            ],
            'types.*.channels' => Rule::forEach(function (mixed $channels, string $attribute) use ($notificationChannels, $notifiable) {
                return [
                    'required',
                    'array',

                    // Restrict each type to the channels it can actually be delivered through for this notifiable.
                    Rule::arrayKeys($notificationChannels->availableNotificationChannelsFor(
                        Str::between($attribute, 'types.', '.channels'),
                        $notifiable,
                    )),
                ];
            }),
            'types.*.channels.*' => [
                'required',
                'boolean',
            ],
        ];

        $validated = $request->validate($rules);

        app(NotificationPreferencesService::class)->updatePreferencesForNotifiable($notifiable, $validated['types']);

        return response()->json(['message' => 'Notification preferences updated successfully.']);
    })->name('notification-preferences.update');
});
