<?php

namespace App\Enums;

enum ProfileVisibility: string
{
    case Public = 'public';
    case Followers = 'followers';
    case Mutuals = 'mutuals';
    case Private = 'private';
}
