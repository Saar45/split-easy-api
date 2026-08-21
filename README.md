<p align="center">
  <img src="docs/assets/logo.png" width="200" alt="SplitEasy">
</p>

# split-easy-api

[![CI](https://github.com/Saar45/split-easy-api/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/Saar45/split-easy-api/actions/workflows/ci.yml)

API REST Symfony de l'application **Split-Easy** — gestion de dépenses partagées entre groupes.
Projet fil rouge CDA niveau 6 (RNCP 37873), IPSSI promotion 2025-2026.

Frontend Angular/Ionic : repo séparé `split-easy-app`.

## Démarrage rapide

```bash
git clone <repo-url> split-easy-api
cd split-easy-api

# 1. Créer .env.local depuis le template — y mettre les vrais mots de passe.
cp .env.example .env.local
$EDITOR .env.local

# 2. Stack complète en dev (avec phpMyAdmin via profile dev).
docker compose --profile dev up -d --build

# 3. Générer la paire de clés JWT (une seule fois).
docker compose exec app php bin/console lexik:jwt:generate-keypair --no-interaction

# 4. Appliquer migrations + fixtures.
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec app php bin/console doctrine:fixtures:load --no-interaction
```

`.env` (commité) ne contient que les variables non sensibles (ports, db name, user).
Les mots de passe et secrets vivent dans `.env.local`, chargé par Symfony **et** par
docker-compose via la directive `env_file`.

### OCR ticket de caisse (F3)

`OCR_SPACE_API_KEY` active `POST /api/expenses/scan-ticket` (extraction montant/date/commerçant
via [OCR.space](https://ocr.space/ocrapi)). Clé gratuite via inscription email, 25 000 requêtes/mois.
Sans clé, l'endpoint répond 503.

| Service     | URL                   | Note                        |
| ----------- | --------------------- | --------------------------- |
| API Symfony | http://localhost:8080 | via nginx                   |
| Front Angular | http://localhost:8100 | dev uniquement (profile dev), build depuis `../split-easy-app` |
| phpMyAdmin  | http://localhost:8081 | dev uniquement (profile dev)|
| MySQL       | localhost:3306        | accès depuis l'hôte         |

Le service `front` attend que le repo `split-easy-app` soit cloné au même niveau que ce repo
(`../split-easy-app`), conformément à la section IV.2.4 du dossier.

Si le port 8080 est déjà occupé sur l'hôte, définir `NGINX_HTTP_PORT` dans `.env.local`
(ex. `NGINX_HTTP_PORT=8090`) avant de lancer `docker compose --profile dev up -d`.

Mode **prod** (sans phpMyAdmin) :
```bash
docker compose up -d
```

## Stack technique

| Composant       | Choix                              |
| --------------- | ---------------------------------- |
| Framework       | Symfony 7.4 LTS                    |
| Langage         | PHP 8.4 (php-fpm-alpine)           |
| ORM             | Doctrine 3.2 + Migrations          |
| Auth            | LexikJWTAuthenticationBundle (RSA) |
| CORS            | NelmioCorsBundle                   |
| BDD             | MySQL 8.0, InnoDB, utf8mb4         |
| Reverse proxy   | nginx 1.27 alpine                  |
| Tests           | PHPUnit                            |

## Structure du projet

```
split-easy-api/
├── bin/                   # console Symfony, phpunit
├── config/                # config bundles
├── docker/
│   ├── nginx/             # default.conf reverse proxy
│   └── php/Dockerfile     # image app (php-fpm)
├── migrations/            # versions Doctrine
├── public/                # entrée HTTP (index.php)
├── src/
│   ├── Controller/
│   ├── DataFixtures/
│   ├── Dto/
│   ├── Entity/
│   ├── Enum/
│   ├── EventSubscriber/
│   ├── Repository/
│   ├── Security/Voter/
│   └── Service/
├── tests/
├── docker-compose.yml     # 4 services : nginx, app, db, phpmyadmin
└── .env.example
```

## Tests

```bash
docker compose exec app php bin/phpunit
```

Suite verte : 183 tests / 999 assertions.

## Comptes de test

Voir `src/DataFixtures/` après `doctrine:fixtures:load`. Le formateur peut se connecter immédiatement avec les comptes seedés (typiquement `alice@test.com` / `SecurePass1` et `bob@test.com` / `SecurePass1` selon les fixtures actives).

## Fonctionnalités livrées (v1.1.1)

| Code | Périmètre |
|------|-----------|
| F1   | Authentification JWT + refresh single-use + consentement CGU horodaté (`cgu_acceptees_le`) |
| F2   | Gestion des groupes (CRUD + rôles créateur/membre) |
| F3   | Dépenses : `POST`/`PUT`/`DELETE` (modification et suppression limitées à 24h après création, verrouillées si un remboursement du groupe est déjà validé) + catégories + `POST /api/expenses/scan-ticket` (OCR.space) |
| F4   | Répartition 3 modes : équitable, personnalisée, pourcentage |
| F5   | Algorithme greedy de réduction des dettes (jury n°1) |
| F6   | Validation bipartite remboursements — machine à 5 états (jury n°2) |
| F7   | Invitations par lien unique (token 7j) |
| F8   | Statistiques agrégées par catégorie / membre / période |
| F9   | Notifications in-app avec référence polymorphe (jury n°3) |
| RGPD | `GET /api/users/me/data` + `DELETE /api/users/me` |

Détails par feature : `docs/features/`. Bilan Jalon 5 : `docs/jalon5-summary.md`.

## Statut

v1.0.0 feature-complete et conforme au dossier v3.0.
