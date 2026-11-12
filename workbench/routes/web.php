<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use SameOldNick\NotificationPreferences\Contracts\NotificationCatalog;
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
        $notificationCatalog = app(NotificationCatalog::class);
        $notificationTypes = app(NotificationPreferencesService::class)
            ->getNotificationTypesForNotifiable($notifiable);

        $rules = [
            'types' => [
                'required',
                'array:'.implode(',', array_keys($notificationTypes)),
            ],
            'types.*.channels' => Rule::forEach(function (mixed $channels, string $attribute) use ($notificationCatalog, $notifiable) {
                return [
                    'required',

                    // Restrict each type to the channels it can actually be delivered through for this notifiable.
                    'array:'.implode(',', $notificationCatalog->availableNotificationChannelsFor(
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
