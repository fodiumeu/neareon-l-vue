<?php

namespace App\Enums;

enum ProfileVisibility: string
{
    case Public = 'public';
    case Members = 'members';
    case Contacts = 'contacts';
    case Followers = 'followers';
    case Mutuals = 'mutuals';
    case Private = 'private';
}
