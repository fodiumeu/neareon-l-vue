# MVP-Demo-Readiness

Stand: v0.10.60-navigation-stable

## Aktuelle MVP-Bereiche

- Home/Dashboard bietet Begrüßung, Schnellzugriffe, offene Punkte, nächste Events und eigene Gruppen.
- Entdecken-Hub führt zu Mitglieder entdecken, Gruppen entdecken und Events entdecken.
- Community-Bereich bündelt Kontakte, Follower, Kontaktanfragen, Blockierungen, Meine Gruppen und Meine Events.
- Gruppen-MVP deckt Discover, Detailseite, Erstellung/Bearbeitung, Mitgliedschaft, Beitrittsanfragen und einfache Rollenverwaltung ab.
- Event-MVP deckt Discover, Detailseite, Erstellung/Bearbeitung, Teilnahme/Anfrage, Anfrageverwaltung, Absage/Wiederherstellung, Meine Events und Benachrichtigungen ab.
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

- Es gibt keinen großen DemoSeeder mit vollständigen, miteinander verknüpften Demo-Nutzern, Gruppen, Events, Nachrichten und Benachrichtigungen.
- Business-/Unternehmensprofile, Monetarisierung, Payment, bezahlte Gruppen/Events und neue Rollen sind nicht Teil des aktuellen MVP.
- Admin/Stammdaten sind für die MVP-Pflege vorhanden, aber nicht als Demo-Hauptbereich ausgebaut.
- Einige Datenstände müssen aktuell über Factories, Tests oder manuelle Nutzung erzeugt werden.

## Empfohlene nächste Schritte vor V0.1.00

- Modul 136B sollte einen kleinen, idempotenten DemoSeeder ergänzen.
- Der DemoSeeder sollte mehrere Nutzer mit Regionen, Sprachen, Interessen, Kontakten, Kontaktanfragen, Gruppenrollen, Event-Teilnahmen, Pending-Anfragen, Nachrichten und Benachrichtigungen erzeugen.
- Eine kurze Demo-Checkliste für Moderation, Privacy und mobile Präsentation wäre sinnvoll.
- Optional kann die Präsentationsumgebung feste Demo-Accounts mit klaren Rollen und Passwörtern erhalten.

## DemoSeeder-Empfehlung

Ein eigener DemoSeeder ist als nächster Schritt sinnvoll. Er sollte klein bleiben, nur synthetische Daten verwenden und keine neuen Tabellen oder Fachlogik einführen.
