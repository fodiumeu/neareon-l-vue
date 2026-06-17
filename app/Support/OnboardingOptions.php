<?php

namespace App\Support;

class OnboardingOptions
{
    /**
     * @return list<string>
     */
    public static function interests(): array
    {
        return [
            'Musik',
            'Sport',
            'Gaming',
            'Reisen',
            'Filme',
            'Serien',
            'Kochen',
            'Fitness',
            'Mode',
            'Fotografie',
            'Lesen',
            'Technologie',
            'Kultur',
            'Sprachen',
            'Familie',
            'Gesundheit',
            'Business / Networking',
            'Lernen',
            'Essen & Ausgehen',
            'Natur',
            'Events',
            'Community',
            'Herkunft / Heritage',
            'Ehrenamt',
            'Kreativität',
        ];
    }

    /**
     * @return list<string>
     */
    public static function languages(): array
    {
        return [
            'Deutsch',
            'Englisch',
            'Türkisch',
            'Arabisch',
            'Französisch',
            'Spanisch',
            'Italienisch',
            'Portugiesisch',
            'Polnisch',
            'Russisch',
            'Ukrainisch',
            'Niederländisch',
            'Kroatisch',
            'Serbisch',
            'Bosnisch',
            'Albanisch',
            'Kurdisch',
            'Persisch/Farsi',
            'Griechisch',
            'Rumänisch',
            'Bulgarisch',
        ];
    }
}
