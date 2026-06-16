# NEAREON Laravel

NEAREON Laravel ist das vorbereitete Laravel-Zielsystem fuer NEAREON. Der aktuelle Base44-MVP bleibt weiterhin die fachliche MVP-, Demo- und Testreferenz, waehrend dieses Repository schrittweise als spaeteres Produktivsystem vorbereitet wird.

## Stack

- Laravel
- Inertia
- Vue
- TypeScript
- Tailwind CSS
- Laravel Fortify
- Pest

## Paketmanager

Dieses Projekt verwendet vorerst npm. `package-lock.json` ist die massgebliche Lockdatei fuer Frontend-Abhaengigkeiten. npm und pnpm sollen nicht gemischt werden.

`pnpm-workspace.yaml` bleibt vorerst unveraendert im Repository und wird in einem spaeteren Aufraeum-Schritt bewusst bewertet.

## Lokale Entwicklung

Voraussetzungen:

- PHP 8.3+
- Composer
- Node.js / npm
- SQLite oder eine andere konfigurierte Laravel-Datenbank

Projekt lokal vorbereiten:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
```

Projekt starten:

```bash
composer run dev
```

Alternativ getrennt:

```bash
php artisan serve
npm run dev
```

## Checks

```bash
npm run format:check
npm run lint:check
npm run types:check
composer test
```

## Aktueller Projektstand

Dieses Repository befindet sich in NEAREON Laravel Phase 0. Es enthaelt Setup-, Branding-, Auth-, Settings-, Rollen- und Admin-Grundlagen aus dem Starterkit, aber noch keine NEAREON-Fachfeatures.

Noch nicht enthalten:

- AgeGate
- NEAREON-Profilmodell
- Discover
- Follow / Unfollow
- Privacy-Logik
- Kontaktanfragen, Messages, Gruppen oder Events
- Profilbilder, Medienverwaltung oder Notifications
- Base44-Datenmigration

## Wichtige Projektstellen

- `.env.example` fuer lokale Standardwerte
- `config/app.php` fuer App-Name, Branding und Projekttexte
- `app/Http/Middleware/HandleInertiaRequests.php` fuer gemeinsame Inertia-Props
- `resources/js/pages/Welcome.vue` fuer die oeffentliche Startseite
- `resources/js/pages/Dashboard.vue` fuer den eingeloggten Phase-0-Einstieg
