<?php

namespace App\Enums;

enum InternalNotificationType: string
{
    case ContactRequestReceived = 'contact_request_received';
    case ContactRequestAccepted = 'contact_request_accepted';
    case ContactRequestDeclined = 'contact_request_declined';
    case GroupJoinRequestReceived = 'group_join_request_received';
    case GroupJoinRequestAccepted = 'group_join_request_accepted';
    case GroupJoinRequestDeclined = 'group_join_request_declined';
    case GroupMemberJoined = 'group_member_joined';
    case NewFollower = 'new_follower';
    case NewMessage = 'new_message';
}
