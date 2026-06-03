# Follow-ups post v1.0

Items identifiés pendant l'audit Jalon 5, déportés après v1.0.0 par décision de périmètre.

## Backend

- Intégration Mindee OCR (`MindeeClient`) — priorité 3 du brief CLAUDE.md, hors périmètre Jalon 5.
- Upload tickets via VichUploaderBundle (champ `chemin_ticket` sur Depense déjà prévu en MLD).
- Envoi email réel des notifications (Mailtrap en dev). Pour l'instant : seul le canal in-app est livré ; la préférence `notifications_email` est persistée pour usage futur.
- Coverage PHPUnit chiffrée (Xdebug/pcov pas activé en container) — instrumenter pour mesure CI > 80%.
- PHPStan niveau 6 + PHP_CodeSniffer PSR-12 dans la CI (mentionnés dans CLAUDE.md mais pas encore installés en `vendor/bin`).

## Frontend

- E2E Cypress/Playwright sur le flow critique (auth → groupe → dépense → soldes → remboursement → notif).
- Capture caméra + preview OCR (dépend du backend Mindee).
- Service worker / push notifications natives (Capacitor) — au-delà du in-app polling.
- Onboarding 3 écrans (Bienvenue, Fonctionnalités, Premier groupe) — non bloquant Jalon 5.

## Cosmétique

- Harmonisation finale des transitions de page (ion-page) sur les routes lazy nouvellement ajoutées.
- Aria-labels exhaustifs sur toutes les actions secondaires.
