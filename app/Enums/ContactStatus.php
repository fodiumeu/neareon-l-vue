<?php

namespace App\Enums;

enum ContactStatus: string
{
    case None = 'none';
    case OutgoingRequest = 'outgoing_request';
    case IncomingRequest = 'incoming_request';
    case Connected = 'connected';
}
