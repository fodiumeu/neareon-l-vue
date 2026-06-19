<?php

namespace App\Enums;

enum ReportReason: string
{
    case Spam = 'spam';
    case Harassment = 'harassment';
    case FakeProfile = 'fake_profile';
    case InappropriateContent = 'inappropriate_content';
    case Fraud = 'fraud';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Spam => 'Spam',
            self::Harassment => 'Belästigung',
            self::FakeProfile => 'Fake-Profil',
            self::InappropriateContent => 'Unangemessene Inhalte',
            self::Fraud => 'Betrug',
            self::Other => 'Sonstiges',
        };
    }
}
