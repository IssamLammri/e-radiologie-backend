# e-Radiologie Backend

Backend de l'application **e-Radiologie**.

La spécification JSON des endpoints de profil et d'administration des utilisateurs est disponible dans [docs/USER_API.md](docs/USER_API.md).

La collection Postman et l'environnement local sont disponibles dans [docs/postman](docs/postman).

Le projet fonctionne avec :

- PHP / Symfony
- PostgreSQL 16
- Doctrine ORM
- Doctrine Migrations
- Docker
- Docker Compose

---

## Prérequis

Avant de démarrer le projet, installer :

- Docker
- Docker Compose
- Make

Vérifier les installations :

```bash
docker --version
docker compose version
make --version
```

---

## Installation

### 1. Récupérer le projet

```bash
git clone <URL_DU_REPOSITORY>
cd <NOM_DU_PROJET>
```

### 2. Configurer les variables d'environnement

Vérifier que les variables PostgreSQL sont présentes dans le fichier `.env` ou `.env.local`.

Exemple :

```dotenv
POSTGRES_DB=e_radiologie
POSTGRES_USER=e_radiologie
POSTGRES_PASSWORD=password
```

La connexion Doctrine doit utiliser le nom du service Docker `database` et le port interne PostgreSQL `5432`.

Exemple :

```dotenv
DATABASE_URL="postgresql://e_radiologie:password@database:5432/e_radiologie?serverVersion=16&charset=utf8"
```

> Depuis les conteneurs Docker, PostgreSQL est accessible via `database:5432`.
>
> Le port `15432` est uniquement utilisé pour accéder à PostgreSQL depuis la machine hôte.

---

## Démarrage initial

Pour installer complètement le projet :

```bash
make install
```

Cette commande effectue automatiquement :

1. la construction des images Docker ;
2. le démarrage de PostgreSQL ;
3. l'attente de la disponibilité de PostgreSQL ;
4. l'installation des dépendances Composer ;
5. la création de la base de données si nécessaire ;
6. l'exécution des migrations Doctrine ;
7. le démarrage des conteneurs ;
8. le nettoyage du cache Symfony.

Une fois l'installation terminée, l'API est disponible à l'adresse :

```text
http://localhost:18740
```

---

## Démarrer le projet

Pour les démarrages suivants :

```bash
make start
```

Vérifier l'état des conteneurs :

```bash
make ps
```

---

## Arrêter le projet

```bash
make stop
```

Pour supprimer les conteneurs :

```bash
make down
```

---

## Redémarrer le projet

```bash
make restart
```

---

## Logs

Afficher les logs de l'application :

```bash
make logs
```

Quitter l'affichage des logs avec :

```text
Ctrl + C
```

---

## Symfony

Afficher les commandes Symfony :

```bash
make console
```

Ouvrir un shell dans le conteneur PHP :

```bash
make shell
```

Il est ensuite possible d'utiliser directement :

```bash
php bin/console
```

---

## Base de données

### Exécuter les migrations

```bash
make migrate
```

### Générer une migration

Après une modification des entités Doctrine :

```bash
make migration
```

Puis appliquer la migration :

```bash
make migrate
```

### Réinitialiser complètement la base

```bash
make db-reset
```

Cette commande :

- supprime la base existante ;
- recrée la base ;
- exécute toutes les migrations.

> Attention : cette commande supprime toutes les données existantes.

---

## Composer

Les dépendances Composer sont automatiquement installées avec :

```bash
make install
```

Pour relancer manuellement l'installation :

```bash
make composer-install
```

---

## Cache Symfony

Pour vider le cache :

```bash
make cache-clear
```

---

## Accès aux services

| Service | Adresse |
| --- | --- |
| Backend Symfony | http://localhost:18740 |
| PostgreSQL depuis Docker | `database:5432` |
| PostgreSQL depuis la machine | `localhost:15432` |

---

## Commandes principales

Afficher toutes les commandes disponibles :

```bash
make
```

ou :

```bash
make help
```

Commandes courantes :

```bash
make install
make start
make stop
make restart
make logs
make shell
make migrate
make migration
make cache-clear
make db-reset
```

---

## Premier lancement

En résumé, après avoir cloné le projet :

```bash
git clone <URL_DU_REPOSITORY>
cd <NOM_DU_PROJET>

make install
```

Puis ouvrir :

```text
http://localhost:18740
```
