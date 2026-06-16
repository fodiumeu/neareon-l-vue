# Cablue Webapp Starter Kit

Laravel, Vue und Inertia als wiederverwendbare Basis fuer kuenftige Webapp-Projekte. Das Repository bleibt bewusst nah an der Laravel-Standardstruktur und erweitert sie nur um eine kleine, saubere AppShell-, Navigations-, Branding- und Admin-Basis.

## Ziel des Starter-Kits

- wiederverwendbare Laravel + Vue + Inertia Grundlage fuer neue Webapps
- keine branchenspezifischen Fachmodule im Kern
- zentrale Stellen fuer Navigation, Branding und grundlegende Designsteuerung
- einfache Rollenbasis fuer spaetere Zugriffsbeschraenkungen
- kleine, risikoarme Weiterentwicklung statt grosser Umbauten

## Lokale Entwicklung

Voraussetzungen:

- PHP 8.3+
- Composer
- Node.js / npm
- SQLite oder eine andere konfigurierte Laravel-Datenbank

Projekt lokal starten:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
composer run dev
```

Alternativ getrennt:

```bash
php artisan serve
npm run dev
```

Nützliche Kommandos:

```bash
php artisan test
npm run lint:check
npm run types:check
```

## Projektstart-Checkliste

Nach dem Klonen oder Uebernehmen des Starter-Kits sollten fuer ein neues Projekt zuerst diese Punkte geprueft werden:

- `APP_NAME`, `APP_LOGO` und die `project`-Werte in [config/app.php](/home/carsten/code/cablue-webapp-kit/config/app.php:1) auf das neue Projekt anpassen
- Branding, `tagline`, `welcome_title`, `welcome_description`, `dashboard_title`, `dashboard_description` und `admin_label` auf Projektbezug pruefen
- den Seed-User in [database/seeders/DatabaseSeeder.php](/home/carsten/code/cablue-webapp-kit/database/seeders/DatabaseSeeder.php:1) anpassen oder entfernen
- Welcome-Seite und Dashboard fuer den echten Projekteinstieg schaerfen
- optionale Bereiche wie Admin und Appearance pruefen und bei Bedarf ueber die `project`-Konfiguration ausblenden
- Navigation, Branding und Projektkonfiguration einmal im Browser durchklicken
- Tests und Qualitaetschecks einmal lokal ausfuehren
  - `php artisan test`
  - `npm run lint:check`
  - `npm run types:check`

## Rollenbasis

Aktuell gibt es eine bewusst schlanke Rollenbasis:

- `member` als Standardrolle
- `admin` fuer geschuetzte Plattformbereiche

Technische Kernstellen:

- [app/Enums/UserRole.php](/home/carsten/code/cablue-webapp-kit/app/Enums/UserRole.php:1)
- [app/Models/User.php](/home/carsten/code/cablue-webapp-kit/app/Models/User.php:1)
- [app/Http/Middleware/EnsureUserHasRole.php](/home/carsten/code/cablue-webapp-kit/app/Http/Middleware/EnsureUserHasRole.php:1)

Der Admin-Zugriff wird aktuell ueber die Route-Middleware `role:admin` geschuetzt.

## Zentrale Projektstellen

Branding:

- [config/app.php](/home/carsten/code/cablue-webapp-kit/config/app.php:1)
- [app/Http/Middleware/HandleInertiaRequests.php](/home/carsten/code/cablue-webapp-kit/app/Http/Middleware/HandleInertiaRequests.php:1)
- [resources/js/components/AppLogo.vue](/home/carsten/code/cablue-webapp-kit/resources/js/components/AppLogo.vue:1)
- [resources/js/app.ts](/home/carsten/code/cablue-webapp-kit/resources/js/app.ts:1)

Navigation:

- [resources/js/config/navigation/app-navigation.ts](/home/carsten/code/cablue-webapp-kit/resources/js/config/navigation/app-navigation.ts:1)
- [resources/js/config/navigation/settings-navigation.ts](/home/carsten/code/cablue-webapp-kit/resources/js/config/navigation/settings-navigation.ts:1)
- [resources/js/types/navigation.ts](/home/carsten/code/cablue-webapp-kit/resources/js/types/navigation.ts:1)
- [resources/js/components/AppSidebar.vue](/home/carsten/code/cablue-webapp-kit/resources/js/components/AppSidebar.vue:1)

Design-Basis:

- [resources/css/app.css](/home/carsten/code/cablue-webapp-kit/resources/css/app.css:1)
- [resources/js/components/PageHeader.vue](/home/carsten/code/cablue-webapp-kit/resources/js/components/PageHeader.vue:1)
- [resources/js/components/PageSection.vue](/home/carsten/code/cablue-webapp-kit/resources/js/components/PageSection.vue:1)

## Project-Konfiguration

Fuer neue Projekte gibt es eine kleine projektnahe Konfigurationsschicht in [config/app.php](/home/carsten/code/cablue-webapp-kit/config/app.php:1) unter `project`.

Die Werte werden in [app/Http/Middleware/HandleInertiaRequests.php](/home/carsten/code/cablue-webapp-kit/app/Http/Middleware/HandleInertiaRequests.php:1) als gemeinsame Inertia-`project`-Prop ins Frontend gegeben.

Aktuell verfuegbare Werte:

- `show_admin_area`
  - steuert, ob der Admin-Bereich in der App-Navigation sichtbar ist
- `show_appearance_settings`
  - steuert, ob der Appearance-Eintrag in der Settings-Navigation sichtbar ist
- `tagline`
  - kleine projektnahe Unterzeile auf der Welcome-Seite
- `welcome_title`
  - Hauptueberschrift der Welcome-Seite
- `welcome_description`
  - Beschreibungstext der Welcome-Seite
- `dashboard_title`
  - Hauptueberschrift der eingeloggten Dashboard-Seite
- `dashboard_description`
  - Beschreibungstext der eingeloggten Dashboard-Seite
- `admin_label`
  - Bezeichnung des Admin-Bereichs in Navigation und Admin-Seite

Damit lassen sich kleine sichtbare Projektanpassungen zentral pflegen, ohne bereits eine groessere Konfigurations- oder Feature-Flag-Struktur einzufuehren.

Das Dashboard kann bei aktiven Starter-Defaults zusaetzlich einen kleinen First-Use-Hinweis anzeigen.

## Aktueller Admin-Bereich

Der Admin-Bereich ist bewusst klein gehalten und dient derzeit als technischer Nachweis fuer:

- rollenabhaengige Navigation
- serverseitig geschuetzte Admin-Route
- kleine read-only Plattformmuster fuer Benutzer, Projektkonfiguration und Systemstatus

Aktuell umfasst der Bereich:

- `Users`
- `Project overview`
- `System status`
  - kann zentrale Starter-Defaults mit einem kleinen `default`-Badge markieren

Wichtige Stellen:

- [routes/web.php](/home/carsten/code/cablue-webapp-kit/routes/web.php:1)
- [app/Http/Controllers/Admin/AdminController.php](/home/carsten/code/cablue-webapp-kit/app/Http/Controllers/Admin/AdminController.php:1)
- [resources/js/pages/Admin.vue](/home/carsten/code/cablue-webapp-kit/resources/js/pages/Admin.vue:1)

Kleine UI-Konvention fuer read-only Admin-/Plattformseiten:

- bestehende `PageHeader`-, `PageSection`- und `Card`-Muster weiterverwenden
- normale Textwerte als klare Label/Wert-Bloecke darstellen
- Boolean- und Statuswerte als Badge darstellen
- fuer Booleans einheitlich `enabled` / `disabled` verwenden
- neue UI-Komponenten erst einfuehren, wenn ein Muster mehrfach wiederkehrt und lokale Wiederholung stoerend wird

Aktuell bewusst nicht enthalten:

- Benutzerbearbeitung
- Rollenbearbeitung im UI
- komplexe Permissions-Engine
- umfangreiche Admin-Navigation

## Entwicklungsprinzip

Das Starter-Kit soll mit wenigen zentralen Aenderungsstellen anpassbar bleiben. Bevor neue Module entstehen, sollten moeglichst erst diese Basispunkte erweitert werden:

- zentrale Navigation
- Branding und App-Metadaten
- globale Design-Basis
- Rollen- und Zugriffsschutz
