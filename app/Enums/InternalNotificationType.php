<?php

namespace App\Enums;

enum InternalNotificationType: string
{
    case ContactRequestReceived = 'contact_request_received';
    case ContactRequestAccepted = 'contact_request_accepted';
    case ContactRequestDeclined = 'contact_request_declined';
    case NewFollower = 'new_follower';
    case NewMessage = 'new_message';
}
