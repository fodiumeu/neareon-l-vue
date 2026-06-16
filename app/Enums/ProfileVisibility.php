<?php

namespace App\Enums;

enum ProfileVisibility: string
{
    case Public = 'public';
    case Mutuals = 'mutuals';
    case Private = 'private';
}
