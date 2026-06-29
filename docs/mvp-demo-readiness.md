# MVP-Demo-Readiness

Stand: v0.10.63-past-event-guard

## Aktuelle MVP-Bereiche

- Home/Dashboard bietet Begrüßung, Schnellzugriffe, offene Punkte, nächste Events und eigene Gruppen.
- Entdecken-Hub führt zu Mitglieder entdecken, Gruppen entdecken und Events entdecken.
- Community-Bereich bündelt Kontakte, Follower, Kontaktanfragen, Blockierungen, Meine Gruppen und Meine Events.
- Gruppen-MVP deckt Discover, Detailseite, Erstellung/Bearbeitung, Mitgliedschaft, Beitrittsanfragen und einfache Rollenverwaltung ab.
- Event-MVP deckt Discover, Detailseite, Erstellung/Bearbeitung, Teilnahme/Anfrage, Anfrageverwaltung, Absage/Wiederherstellung, Meine Events und Benachrichtigungen ab.
- Events entdecken zeigt aktive public/request Events nur, solange sie kommend oder laufend sind; vergangene Events bleiben von Discover und neuen Teilnahmen ausgeschlossen.
- Nachrichten, Benachrichtigungen, Profil, Profilbearbeitung, Einstellungen und Onboarding sind als MVP-Flows vorhanden.

## Vorführbare Demo-Flows

- Onboarding mit Profilangaben, Sprachen und Interessen.
- Mitglieder entdecken, Profil ansehen, folgen, Kontaktanfrage senden und beantworten.
- Community-Übersicht öffnen und Unterseiten mit stabilen Rückwegen nutzen.
- Gruppe erstellen, Gruppe entdecken, beitreten oder Beitritt anfragen, Mitglieder verwalten.
- Event erstellen, Event entdecken, teilnehmen oder Teilnahme anfragen, Anfragen annehmen/ablehnen, Event absagen und wieder aktivieren.
- Home als Einstieg verwenden und von dort per sicherem Kontext zu Listen- und Detailseiten zurückkehren.
- Nachrichtenübersicht und Benachrichtigungen als Kommunikations- und Aktivitätszentrale nutzen.

## Bewusste MVP-Grenzen

- Der DemoSeeder bleibt bewusst kompakt und synthetisch; er ersetzt keine produktive Datenpflege und keine fachliche Rollen-/Payment-Erweiterung.
- Business-/Unternehmensprofile, Monetarisierung, Payment, bezahlte Gruppen/Events und neue Rollen sind nicht Teil des aktuellen MVP.
- Admin/Stammdaten sind für die MVP-Pflege vorhanden, aber nicht als Demo-Hauptbereich ausgebaut.
- Einige Datenstände müssen aktuell über Factories, Tests oder manuelle Nutzung erzeugt werden.

## Empfohlene nächste Schritte vor V0.1.00

- Demo-Daten sollten fuer Praesentationen regelmaessig frisch geseedet werden.
- Sichtbare Demo-Events in Entdecken sollten kommende Termine bleiben, damit die MVP-Oberflaeche nicht wie ein Altbestand wirkt.
- Eine kurze Demo-Checkliste für Moderation, Privacy und mobile Präsentation wäre sinnvoll.
- Optional kann die Präsentationsumgebung feste Demo-Accounts mit klaren Rollen und Passwörtern erhalten.

## DemoSeeder-Empfehlung

Der eigene DemoSeeder bleibt klein, idempotent, synthetisch und nicht automatisch in `DatabaseSeeder` verdrahtet. Er erzeugt Demo-Nutzer, Profile, Kontakte, Gruppen, Events, Nachrichten und Benachrichtigungen ohne neue Tabellen oder Fachlogik.
