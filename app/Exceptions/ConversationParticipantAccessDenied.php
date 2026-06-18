<?php

namespace App\Exceptions;

use DomainException;

class ConversationParticipantAccessDenied extends DomainException
{
    public function __construct()
    {
        parent::__construct('Only conversation participants may access its read state.');
    }
}
