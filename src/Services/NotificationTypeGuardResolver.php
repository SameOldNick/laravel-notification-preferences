<?php

namespace SameOldNick\NotificationPreferences\Services;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use SameOldNick\NotificationPreferences\Contracts\NotificationTypeGuard;

class NotificationTypeGuardResolver
{
    /**
     * Create a new instance of the NotificationTypeGuardResolver.
     *
     * @return void
     */
    public function __construct(
        private readonly Repository $config,
        private readonly Container $container,
    ) {
        //
    }

    /**
     * Resolve the configured notification type guard, if one is configured.
     *
     * A null or missing guard leaves every type unguarded. Any other value must be a
     * class-string implementing the contract, so misconfiguration fails loudly. Naming
     * the contract itself resolves whichever implementation is bound to it.
     */
    public function resolve(): ?NotificationTypeGuard
    {
        $guard = $this->config->get('notification-preferences.guard');

        if ($guard === null) {
            return null;
        }

        if (! is_string($guard) || ! is_a($guard, NotificationTypeGuard::class, true)) {
            throw new InvalidArgumentException(sprintf(
                'The [notification-preferences.guard] configuration value must be a class-string implementing [%s], %s given.',
                NotificationTypeGuard::class,
                is_string($guard) ? $guard : get_debug_type($guard),
            ));
        }

        if ($guard === NotificationTypeGuard::class && ! $this->container->bound($guard)) {
            throw new InvalidArgumentException(sprintf(
                'The [notification-preferences.guard] configuration value is the [%s] contract, but no implementation is bound to the container. Bind an implementation or set the guard to null to leave types unguarded.',
                NotificationTypeGuard::class,
            ));
        }

        $instance = $this->container->make($guard);

        if (! $instance instanceof NotificationTypeGuard) {
            throw new InvalidArgumentException(sprintf(
                'The [notification-preferences.guard] configuration value resolved to [%s], which does not implement [%s].',
                get_debug_type($instance),
                NotificationTypeGuard::class,
            ));
        }

        return $instance;
    }
}
