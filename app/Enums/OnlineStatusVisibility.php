<?php

namespace App\Enums;

enum OnlineStatusVisibility: string
{
    case Nobody = 'nobody';
    case Contacts = 'contacts';
    case MutualContacts = 'mutual_contacts';
}
