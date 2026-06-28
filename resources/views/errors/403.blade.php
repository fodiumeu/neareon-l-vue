<x-errors.layout
    code="403"
    title="Zugriff verweigert"
    message="Du hast keine Berechtigung, diese Seite aufzurufen."
    primary-label="Zur Startseite"
    :primary-url="url('/')"
    secondary-label="Gruppen entdecken"
    :secondary-url="url('/groups')"
/>
