<?php

namespace App\Exceptions;

use DomainException;

class SelfConversationNotAllowed extends DomainException
{
    public function __construct()
    {
        parent::__construct('A direct conversation requires two different users.');
    }
}
