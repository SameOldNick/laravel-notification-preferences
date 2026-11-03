<?php

namespace SameOldNick\NotificationPreferences;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use SameOldNick\NotificationPreferences\Contracts\NotificationCatalog;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(NotificationCatalog::class, Providers\NotificationConfigProvider::class);

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'notification-preferences-migrations');

        // Merge the package configuration so that the default driver is set, but allow the user to override it in their own config.
        $this->mergeConfigFrom(__DIR__.'/../config/notifications.php', 'notifications');
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/notifications.php' => config_path('notifications.php'),
        ], 'notification-preferences-config');
    }
}
