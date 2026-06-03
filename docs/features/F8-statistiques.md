# F8 — Statistiques

Référence dossier : §III.5 (F8) et §6.5 (modèle de données).

## Périmètre livré

- Endpoint `GET /api/stats?period=semaine|mois|annee&group_id=<optional>` (JWT requis).
- Endpoint `GET /api/categories` (JWT requis) qui expose la table `categorie` au frontend, et remplace les constantes codées en dur côté Angular.
- Service `StatisticsService` qui agrège les dépenses où l'utilisateur connecté est payeur, restreintes aux groupes dont il est membre accepté (ou à un groupe précis si `group_id` est fourni).

## Granularité d'évolution

| Période | Fenêtre | Granularité |
|---------|---------|-------------|
| `semaine` | Les 7 derniers jours (inclus aujourd'hui) | Un point par jour |
| `mois` | Mois calendaire courant | Un point par jour |
| `annee` | Les 12 derniers mois | Un point par mois (premier du mois) |

Les bornes sont calculées dans `StatisticsService::resolveRange()`.

## Shape de réponse `/api/stats`

```json
{
  "periode": "mois",
  "date_debut": "2026-06-01",
  "date_fin": "2026-06-30",
  "total_depense": "523.40",
  "moyenne_par_jour": "16.88",
  "categorie_principale": { "id": 1, "nom": "Courses", "couleur": "#4CAF50", "montant": "245.20" },
  "par_categorie": [
    { "id": 1, "nom": "Courses", "couleur": "#4CAF50", "montant": "245.20", "pourcentage": "46.85" }
  ],
  "evolution": [
    { "date": "2026-06-01", "montant": "12.50" }
  ]
}
```

Tous les montants sont des chaînes décimales (BCMath, 2 chiffres) pour préserver la précision.

## Règle de distribution des pourcentages

La somme des pourcentages de `par_categorie` est garantie exactement à `100.00` : le reliquat d'arrondi est attribué à la dernière catégorie de la liste (qui est la moins importante en montant, car la liste est triée DESC).

Couvert par le test unitaire `StatisticsServiceTest::testParCategoriePourcentagesSumToOneHundred`.

## Autorisations

- `GET /api/stats` sans `group_id` : agrège uniquement les dépenses de l'utilisateur dans les groupes où il a `statutInvitation = acceptee`.
- `GET /api/stats?group_id=N` : 404 si le groupe n'existe pas, 403 si l'utilisateur n'est pas membre accepté, 200 sinon. Le check est fait dans `StatisticsService::resolveGroupes()` via `AppartenirRepository`.

## Tests

- Unitaires : `tests/Unit/Service/StatisticsServiceTest.php` (7 cas, bornes de période, somme pourcentages, état vide, granularité d'évolution).
- Fonctionnels : `tests/Functional/Controller/StatisticsControllerTest.php` (9 cas, 401/403/400, agrégation multi-catégories, scoping `group_id`, endpoint categories).

## Fichiers clés (backend)

- `src/Enum/PeriodeStatistique.php`
- `src/Repository/DepenseRepository.php` (méthodes `sumByCategoryForPayer`, `findRawAmountsForPayer`)
- `src/Service/StatisticsService.php`
- `src/Controller/StatisticsController.php`
- `src/Controller/CategoryController.php`

## Frontend (split-easy-app)

- `chart.js` + `ng2-charts` installés.
- `src/app/core/models/statistics.model.ts` — interfaces miroir.
- `src/app/core/services/statistics.service.ts` — wrapper HTTP.
- `src/app/core/services/category.service.ts` — nouveau service avec cache `shareReplay`, remplace `DEFAULT_CATEGORIES` (renommé `FALLBACK_CATEGORIES`, conservé comme fallback en cas d'erreur HTTP).
- `src/app/features/statistiques/statistiques.page.*` — page câblée :
  - Doughnut chart sur `par_categorie`.
  - Line chart sur `evolution`.
  - Liste détaillée par catégorie avec barres de progression colorées (couleur depuis la BDD).
  - Trois cartes résumé : total, moyenne par jour, catégorie principale.
  - Changement de période déclenche un refetch (`setPeriod()` court-circuite si la valeur n'a pas changé).

## PRs

- Backend : Saar45/split-easy-api#12
- Frontend : Saar45/split-easy-app#14
