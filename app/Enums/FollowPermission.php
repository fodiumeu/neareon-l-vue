<?php

namespace App\Enums;

enum FollowPermission: string
{
    case Everyone = 'everyone';
    case Members = 'members';
    case Nobody = 'nobody';
}
