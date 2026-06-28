<x-errors.layout
    code="404"
    title="Seite nicht gefunden"
    message="Die angeforderte Seite konnte nicht gefunden werden. Möglicherweise ist der Link ungültig oder nicht mehr verfügbar."
    primary-label="Zur Startseite"
    :primary-url="url('/')"
    secondary-label="Gruppen entdecken"
    :secondary-url="url('/groups')"
/>
