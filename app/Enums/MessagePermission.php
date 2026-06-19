<?php

namespace App\Enums;

enum MessagePermission: string
{
    case ContactsOnly = 'contacts_only';
    case ExistingConversations = 'existing_conversations';
}
