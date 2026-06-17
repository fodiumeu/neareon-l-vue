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
            ['code' => 'uk', 'label' => 'Ukrainisch', 'native_label' => 'Українська'],
            ['code' => 'nl', 'label' => 'Niederländisch', 'native_label' => 'Nederlands'],
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
