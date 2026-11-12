<?php

namespace SameOldNick\NotificationPreferences\Attributes;

use Attribute;
use BackedEnum;

#[Attribute(Attribute::TARGET_CLASS)]
final class NotificationType
{
    public function __construct(public readonly string|BackedEnum $type)
    {
        //
    }
}
