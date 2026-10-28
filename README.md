# e-Radiologie Backend

API Symfony / PHP 8.4, Doctrine ORM et MySQL 8.4.

La spécification des endpoints utilisateurs est disponible dans
[docs/USER_API.md](docs/USER_API.md), et la collection Postman dans
[docs/postman](docs/postman).

## Lancer les trois projets

Depuis le dossier parent `E-RADIOLOGIE`, exécuter `make start`.
Le [guide local commun](../README.md) décrit les ports, les comptes de test et
les commandes pour le site client, le back-office et phpMyAdmin.

## Lancer seulement le backend et sa base

Prérequis : Docker, Docker Compose et Make.

```bash
make start
make logs
```

Le conteneur PHP installe les dépendances Composer, génère les clés JWT si elles
manquent, applique les migrations MySQL et crée les deux comptes de test locaux.
Il démarre ensuite le serveur Symfony sur http://localhost:18740.

| Service | Adresse |
| --- | --- |
| API publique | http://localhost:18740/api/cases |
| Documentation | http://localhost:18740/api/docs |
| phpMyAdmin | http://localhost:18741 |
| MySQL depuis Docker | `database:3306` |
| MySQL depuis la machine | `127.0.0.1:13306` |

phpMyAdmin : utilisateur `e_radiologie`, mot de passe `e_radiologie_local`,
base `e_radiologie`. Les comptes applicatifs sont `admin@e-radiologie.test`
et `client@e-radiologie.test`, avec le mot de passe `LocalTest123!`.

## Configuration

Copier `.env.example` vers `.env.local` au premier clonage, puis renseigner les
valeurs de la machine cible. Ce fichier est ignoré par Git. Compose le charge
pour PHP et Make utilise `--env-file .env.local` pour les variables MySQL et
phpMyAdmin. Aucune URL, configuration email ou clé JWT n'est imposée par Docker.

```dotenv
DATABASE_URL="mysql://e_radiologie:e_radiologie_local@database:3306/e_radiologie?serverVersion=8.4.0&charset=utf8mb4"
```

La configuration Docker est réservée au développement local. Les comptes de test
ne sont créés que si `APP_ENV=dev` et `APP_LOCAL_SETUP=1`.

En staging/production, utiliser `APP_ENV=prod`, `APP_DEBUG=0`, `APP_LOCAL_SETUP=0`,
des secrets dédiés et les véritables URL dans `DEFAULT_URI` et `FRONTEND_URL`.
Adapter `CORS_ALLOW_ORIGIN` aux domaines client et administrateur. Renseigner
`PHPMYADMIN_URL` si phpMyAdmin est accessible derrière un proxy.

Pour Brevo, définir `BREVO_API_KEY`,
`MAILER_DSN=brevo+api://${BREVO_API_KEY}@default` et un `MAIL_FROM_EMAIL` autorisé.
`MAILER_DSN=null://null` désactive les envois. Ne pas mettre de clé Brevo dans
les variables publiques `VITE_*` des interfaces.

Après modification, lancer `make start` (recréation des conteneurs concernés),
ou `docker compose --env-file .env.local up -d --build --wait`.
Le workflow de déploiement utilise cette même commande : dépendances, migrations
et cache sont gérés dans le script de démarrage ; aucun compte local n'est créé
en mode production. Le serveur PHP intégré reste destiné au développement et
doit être remplacé par un serveur adapté pour une exposition en production.

Les mots de passe `MYSQL_*` initialisent un volume neuf ; leur modification
n'altère pas les comptes d'un volume existant. Maintenir `DATABASE_URL` cohérent
avec les identifiants réellement configurés dans MySQL.

## Migrations et données

Les migrations actives se trouvent dans `migrations/mysql` et créent l'ensemble
des tables ainsi que les modalités et catégories initiales. Les anciennes
migrations PostgreSQL restent archivées dans `migrations` ; elles ne sont plus
chargées par Doctrine. Une base PostgreSQL existante n'est pas convertie par ce
processus. Aucun ancien volume PostgreSQL n'est supprimé.

MySQL conserve la règle d'un seul média principal par cas grâce à une colonne
générée et un index unique, tout en autorisant plusieurs médias secondaires.

```bash
make migrate       # appliquer les migrations
make migration     # générer une migration après modification des entités
make cache-clear
make shell
make console
make ps
make stop
make down          # conserver le volume MySQL
```

`make db-reset` supprime toutes les données de la base locale. `make clean`
supprime également le volume Docker MySQL.

## Vérification

```bash
docker compose --env-file .env.local exec -T php composer test
docker compose --env-file .env.local exec -T php composer test:integration
docker compose --env-file .env.local exec -T php php bin/console doctrine:schema:validate
```

Les tests d'intégration utilisent une transaction annulée à la fin. Ils vérifient
notamment le catalogue public, la protection des routes administrateur et
l'unicité du média principal dans MySQL.

## Modules V1

Voir le [contrat API V1](docs/V1_API.md) pour les classifications, cours, planchages,
examens, modèles et comptes rendus privés. La migration V1 est appliquée au
démarrage Docker, en conservant les données déjà présentes. Les tests HTTP V1
se lancent avec `docker compose --env-file .env.local exec -T php composer test:v1`.
