<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
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
        $validated = $request->validate([
            'types' => [
                'required',
                'array',
                Rule::arrayKeys(array_keys(app(NotificationTypeProvider::class)->notificationTypes())),
            ],
            'types.*.channels' => [
                'required',
                Rule::arrayKeys(app(NotificationChannelProvider::class)->availableNotificationChannels()),
            ],
            'types.*.channels.*' => [
                'required',
                'boolean',
            ],
        ]);

        app(NotificationPreferencesService::class)->updatePreferencesForNotifiable($request->user(), $validated['types']);

        return response()->json(['message' => 'Notification preferences updated successfully.']);
    })->name('notification-preferences.update');
});
