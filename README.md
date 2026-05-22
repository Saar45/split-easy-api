# split-easy-api

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

| Service     | URL                   | Note                        |
| ----------- | --------------------- | --------------------------- |
| API Symfony | http://localhost:8080 | via nginx                   |
| phpMyAdmin  | http://localhost:8081 | dev uniquement (profile dev)|
| MySQL       | localhost:3306        | accès depuis l'hôte         |

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

## Comptes de test

À compléter dès que les fixtures utilisateurs seront en place.

## Statut Jalon 5

Squelette + base de données initialisés. Prochaines étapes : authentification F1, gestion des groupes F2.
