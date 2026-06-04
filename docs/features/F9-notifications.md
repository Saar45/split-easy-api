# F9 — Notifications in-app

Référence dossier : §III.5 (F9), §6.5 (entité Notification — point de défense jury n°3 référence polymorphe).

## Périmètre livré

- Entité `Notification` avec référence polymorphe `(reference_type, reference_id)` sans contrainte FK, intégrité garantie au niveau Service.
- Enum `TypeNotification` étendue à 8 valeurs : `invitation_recue`, `invitation_acceptee`, `invitation_refusee`, `depense_ajoutee`, `remboursement_propose`, `remboursement_accepte`, `remboursement_rejete`, `remboursement_annule`.
- Service `NotificationService` (create, markAsRead, markAllAsRead, listForUser, countUnread).
- Voter `NotificationVoter` (READ) : seul le destinataire peut marquer une notification comme lue.
- Hooks dans `InvitationService`, `ExpenseService`, `ReimbursementService` : chaque action métier émet une notif in-app aux destinataires concernés.

## Endpoints

| Méthode | URL                                  | Réponse                          |
|---------|--------------------------------------|----------------------------------|
| GET     | `/api/notifications?unread=true|false&limit=50` | tableau `AppNotification[]` trié DESC date_creation |
| GET     | `/api/notifications/unread-count`    | `{ count: int }`                |
| POST    | `/api/notifications/{id}/read`       | 204 No Content                  |
| POST    | `/api/notifications/read-all`        | `{ updated: int }`              |

## Shape de réponse

```json
{
  "id": 42,
  "type": "depense_ajoutee",
  "titre": "Nouvelle dépense",
  "message": "Alice D a ajouté « Pizza » (30.00 €) dans le groupe « Coloc ».",
  "lue": false,
  "date_creation": "2026-06-03T12:00:00+00:00",
  "date_lecture": null,
  "reference_type": "depense",
  "reference_id": 17
}
```

## Référence polymorphe (point de défense jury n°3)

Le couple `(reference_type, reference_id)` pointe sans contrainte FK vers :
- `appartenir` + group_id pour les notifs d'invitation (PK composite, on stocke le group_id pour le routage UI)
- `depense` + depense_id pour `depense_ajoutee`
- `remboursement` + remboursement_id pour le lifecycle remboursement

Avantages architecturaux défendus :
- Une seule table couvre N types d'événements (extensible sans migration sur ajout de type).
- Pas de circulation FK : la notification survit à la suppression d'une entité liée (soft-delete safe pour les logs).
- L'intégrité référentielle est garantie par `NotificationService::create()` qui n'est appelé qu'après persistance de l'entité cible.

## Frontend

- `core/services/notification.service.ts` : poll toutes les 30s via `interval(30000).pipe(switchMap(unreadCount))`. `BehaviorSubject<number>` exposé via `unreadCount$`.
- `features/notifications/` : module lazy-loaded sur `/tabs/notifications`. Liste icônée par type, badge "Nouveau" si non lue, tap → mark as read + navigate vers l'entité liée, pull-to-refresh, bouton "Tout marquer comme lu".
- Cloche `notifications-outline` dans le header Accueil avec badge danger affichant le compteur (9+ si > 9).

## Tests

- `tests/Unit/Service/NotificationServiceTest.php` : 6 cas (create persiste, markAsRead/idempotent, markAllAsRead, listForUser bornage limit, countUnread).
- `tests/Functional/Controller/NotificationControllerTest.php` : 7 cas (invitation crée notif, unread-count, filter unread, 403 wrong user, mark-all, expense crée notif aux autres membres, remboursement lifecycle propose+accept).
- `src/app/core/services/notification.service.spec.ts` : 5 cas Karma sur tous les verbes HTTP + BehaviorSubject.
