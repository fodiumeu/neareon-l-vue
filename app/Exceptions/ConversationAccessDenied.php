<?php

namespace App\Exceptions;

use DomainException;

class ConversationAccessDenied extends DomainException
{
    public function __construct()
    {
        parent::__construct('A direct conversation requires a mutual follow.');
    }
}
