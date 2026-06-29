# NEAREON DemoSeeder

Der `DemoSeeder` erzeugt optionale, synthetische MVP-Daten fuer lokale Demos und Praesentationspruefungen. Er ist primaer fuer eine vorfuehrbare MVP-Oberflaeche gedacht und bewusst nicht in `DatabaseSeeder` verdrahtet.

Ausfuehren:

```bash
php artisan db:seed --class=DemoSeeder
```

Der Seeder bricht in der `production`-Umgebung ab. Alle Demo-Accounts nutzen die Domain `@neareon.test`, externe Bild-URLs werden nicht gesetzt.

Demo-Login:

- `demo.fodi@neareon.test`
- `demo.mira@neareon.test`
- `demo.jonas@neareon.test`
- `demo.lea@neareon.test`
- `demo.admin@neareon.test`

Gemeinsames Passwort:

```text
neareon-demo
```

Der Datensatz enthaelt onboarded Profile mit Sprachen und Interessen, Kontakte/Follows, offene Kontaktanfragen, oeffentliche und anfragebasierte Gruppen, private Demo-Gruppen, kommende und abgesagte Events, eine Demo-Unterhaltung sowie interne Benachrichtigungen. Sichtbare Entdecken-Events werden als kommende Demo-Termine gehalten, damit die Praesentationsansicht aktuell wirkt.
