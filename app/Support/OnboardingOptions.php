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
            'Business / Networking',
            'Lernen',
            'Essen & Ausgehen',
            'Natur',
            'Events',
            'Community',
            'Herkunft / Heritage',
            'Ehrenamt',
            'Kreativitaet',
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
            'Tuerkisch',
            'Arabisch',
            'Franzoesisch',
            'Spanisch',
            'Italienisch',
            'Portugiesisch',
            'Polnisch',
            'Russisch',
            'Ukrainisch',
            'Niederlaendisch',
            'Kroatisch',
            'Serbisch',
            'Bosnisch',
            'Albanisch',
            'Kurdisch',
            'Persisch/Farsi',
            'Griechisch',
            'Rumaenisch',
            'Bulgarisch',
        ];
    }
}
