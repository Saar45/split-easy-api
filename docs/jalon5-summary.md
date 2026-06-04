# Jalon 5 — Bilan de livraison Split-Easy v1.0.0

> Repos : `split-easy-api` (Symfony 7.4 / PHP 8.4 / MySQL 8) et `split-easy-app` (Angular 17 / Ionic).
> Échéance Jalon 5 : 29 mai 2026. Livraison effective le 3 juin 2026.

## Fonctionnalités livrées

| Code | Périmètre | Statut |
|------|-----------|--------|
| F1   | Authentification : register + login JWT + refresh single-use + RGPD CGU | livré |
| F2   | Groupes : CRUD + rôle créateur/membre | livré |
| F3   | Dépenses : CRUD avec catégories | livré |
| F4   | Répartition 3 modes : équitable, personnalisée, pourcentage (surplus arrondi au payeur) | livré |
| F5   | Algorithme greedy de réduction des dettes (point de défense jury n°1) | livré |
| F6   | Validation bipartite des remboursements — machine à 5 états (jury n°2) | livré |
| F7   | Invitations par lien unique (token 7j) | livré |
| F8   | Statistiques agrégées par catégorie / membre / période | livré |
| F9   | Notifications in-app avec référence polymorphe (jury n°3) | livré |
| RGPD | Export `/api/users/me/data` + suppression `/api/users/me` | livré |

## Tests automatisés

- **Backend (PHPUnit)** : 143 tests, 836 assertions, suite verte.
  - Unitaires services : Auth, DebtOptimizer, Invitation, Notification, Reimbursement, SplitCalculator, Statistics.
  - Fonctionnels controllers : Auth, Balances, Dashboard, Expense, Group, Invitation, Notification, Remboursement, Statistics, UserMe.
- **Frontend (Karma)** : 67 specs Jasmine, suite verte. Services HTTP couverts par HttpTestingController.
- **CI GitHub Actions** : lint YAML + lint container + boot Symfony + ng lint + ng test + ng build production. Pipeline verte sur les deux repos.

## Points de défense jury (préservés dans le code)

1. **Greedy** (F5) — `DebtOptimizerService::optimize()` : appariement crédit max / dette max à chaque itération, transactions bornées par n-1. Commenté en français.
2. **Machine à états** (F6) — `ReimbursementService` : transitions Propose → Valide / Conteste / Annule, transitions invalides rejetées en 409.
3. **Référence polymorphe** (F9) — `Notification.reference_type` + `reference_id` sans FK ; intégrité gérée par `NotificationService::create()`. Permet une seule table pour 8 types d'événements.

## Conformité dossier v3.0

- Stack technique conforme §3.4.2 : Symfony LTS, Doctrine, Lexik JWT, NelmioCors, Argon2id.
- MLD §VI respecté à la lettre (8 entités + tables de liaison Appartenir/Repartir).
- Architecture §VIII : Controller → Service → Repository, DI stricte, voters Symfony pour A01.
- Charte graphique §6 appliquée via tokens CSS (variables.scss). Aucune couleur hardcodée.
- Navigation 5 tabs §V.4 : Accueil / Groupes / [+] / Statistiques / Profil + bell Notifications dans le header.

## RGPD checklist

- [x] Consentement CGU horodaté à l'inscription
- [x] Export JSON complet des données personnelles
- [x] Suppression compte avec cascade ON DELETE
- [x] Argon2id pour les mots de passe
- [x] JWT en mémoire RAM côté frontend (pas localStorage)
- [x] Préférences `notifications_email` / `notifications_push` persistées
- [x] Politique de confidentialité référencée

## Sécurité OWASP Top 10 2021

| Code | Mesure | Statut |
|------|--------|--------|
| A01 | Voters granulaires + appartenance groupe | OK |
| A02 | Argon2id auto | OK |
| A03 | Doctrine paramètres bindés + Validator sur DTO | OK |
| A05 | Headers nginx (X-Frame, X-Content-Type, Referrer-Policy) | OK |
| A07 | Rate limiter login 5/15min + JWT 1h + refresh rotation | OK |
| A09 | Monolog rotation quotidienne | OK |

## Tags

- `v0.9-beta-jalon5` : livraison Jalon 5 historique.
- `v0.97-f9` : F9 notifications mergée.
- `v1.0.0` : version feature-complete, dossier-conformante.

## Items déférés (voir `docs/follow-ups.md`)

- Intégration Mindee OCR (priorité 3, non bloquante).
- Envoi email réel (canal in-app suffisant pour le jury).
- E2E Cypress/Playwright.
- PHPStan + PHP_CodeSniffer dans la CI.

## Démo

- Stack locale : `docker compose --profile dev up` dans split-easy-api, `npm start` dans split-easy-app.
- Comptes de test seedés dans les fixtures (voir README de chaque repo).
- 5 tabs opérationnels : login → Accueil (bell + badge unread) → Groupes → [+] action sheet → Statistiques (doughnut + line) → Profil → Notifications.
