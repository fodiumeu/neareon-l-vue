<?php

namespace App\Enums;

enum ContactPermission: string
{
    case Everyone = 'everyone';
    case Followers = 'followers';
    case Nobody = 'nobody';
}
