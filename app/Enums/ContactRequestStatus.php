<?php

namespace App\Enums;

enum ContactRequestStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Closed = 'closed';
}
