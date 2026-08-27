<?php

namespace App\Enums;

enum DeviceType: string
{
    case DESKTOP = 'desktop';
    case MOBILE = 'mobile';
    case TABLET = 'tablet';
    case BOT = 'bot';
}
