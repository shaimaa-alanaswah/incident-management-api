<?php

namespace App\Enums;

enum NotificationChannel: string
{
    case Email = 'email';
    case Slack = 'slack';
    case Webhook = 'webhook';
}
