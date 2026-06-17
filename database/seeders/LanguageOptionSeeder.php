<?php

namespace Database\Seeders;

use App\Models\LanguageOption;
use Illuminate\Database\Seeder;

class LanguageOptionSeeder extends Seeder
{
    /**
     * Seed the MVP language option catalog.
     */
    public function run(): void
    {
        $languages = [
            ['code' => 'de', 'label' => 'Deutsch', 'native_label' => 'Deutsch'],
            ['code' => 'en', 'label' => 'Englisch', 'native_label' => 'English'],
            ['code' => 'tr', 'label' => 'Türkisch', 'native_label' => 'Türkçe'],
            ['code' => 'ar', 'label' => 'Arabisch', 'native_label' => 'العربية'],
            ['code' => 'fr', 'label' => 'Französisch', 'native_label' => 'Français'],
            ['code' => 'es', 'label' => 'Spanisch', 'native_label' => 'Español'],
            ['code' => 'it', 'label' => 'Italienisch', 'native_label' => 'Italiano'],
            ['code' => 'pt', 'label' => 'Portugiesisch', 'native_label' => 'Português'],
            ['code' => 'pl', 'label' => 'Polnisch', 'native_label' => 'Polski'],
            ['code' => 'ru', 'label' => 'Russisch', 'native_label' => 'Русский'],
            ['code' => 'uk', 'label' => 'Ukrainisch', 'native_label' => 'Українська'],
            ['code' => 'nl', 'label' => 'Niederländisch', 'native_label' => 'Nederlands'],
            ['code' => 'hr', 'label' => 'Kroatisch', 'native_label' => 'Hrvatski'],
            ['code' => 'sr', 'label' => 'Serbisch', 'native_label' => 'Српски'],
            ['code' => 'bs', 'label' => 'Bosnisch', 'native_label' => 'Bosanski'],
            ['code' => 'sq', 'label' => 'Albanisch', 'native_label' => 'Shqip'],
            ['code' => 'ku', 'label' => 'Kurdisch', 'native_label' => 'Kurdî'],
            ['code' => 'fa', 'label' => 'Persisch/Farsi', 'native_label' => 'فارسی'],
            ['code' => 'el', 'label' => 'Griechisch', 'native_label' => 'Ελληνικά'],
            ['code' => 'ro', 'label' => 'Rumänisch', 'native_label' => 'Română'],
            ['code' => 'bg', 'label' => 'Bulgarisch', 'native_label' => 'Български'],
        ];

        foreach ($languages as $index => $language) {
            LanguageOption::query()->updateOrCreate(
                ['code' => $language['code']],
                [
                    'label' => $language['label'],
                    'native_label' => $language['native_label'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ],
            );
        }
    }
}
