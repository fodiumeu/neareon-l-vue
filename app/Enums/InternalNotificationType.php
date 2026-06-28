<?php

namespace App\Enums;

enum InternalNotificationType: string
{
    case ContactRequestReceived = 'contact_request_received';
    case ContactRequestAccepted = 'contact_request_accepted';
    case ContactRequestDeclined = 'contact_request_declined';
    case EventAttendanceRequestReceived = 'event_attendance_request_received';
    case EventAttendanceRequestAccepted = 'event_attendance_request_accepted';
    case EventAttendanceRequestDeclined = 'event_attendance_request_declined';
    case EventAttendeeJoined = 'event_attendee_joined';
    case GroupJoinRequestReceived = 'group_join_request_received';
    case GroupJoinRequestAccepted = 'group_join_request_accepted';
    case GroupJoinRequestDeclined = 'group_join_request_declined';
    case GroupMemberJoined = 'group_member_joined';
    case GroupMemberRemoved = 'group_member_removed';
    case GroupModeratorPromoted = 'group_moderator_promoted';
    case GroupModeratorDemoted = 'group_moderator_demoted';
    case NewFollower = 'new_follower';
    case NewMessage = 'new_message';
}
