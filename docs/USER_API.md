# API profil et administration des utilisateurs

Toutes les routes protégées attendent l'en-tête suivant :

```http
Authorization: Bearer <JWT>
Content-Type: application/json
```

Les routes `/api/admin/users` nécessitent `ROLE_ADMIN`. Un utilisateur non connecté reçoit `401`; un utilisateur connecté sans ce rôle reçoit `403`.

## Profil de l'utilisateur connecté

### Lire son profil

`GET /api/auth/me`

Réponse `200` :

```json
{
  "id": 12,
  "email": "user@example.com",
  "firstName": "Jean",
  "lastName": "Dupont",
  "roles": ["ROLE_USER"]
}
```

### Modifier son profil

`PATCH /api/auth/me`

Tous les champs sont optionnels; envoyer uniquement ceux à modifier. Un utilisateur ne peut pas modifier ses rôles avec cette route.

```json
{
  "firstName": "Jeanne",
  "lastName": "Dupont",
  "email": "jeanne.dupont@example.com"
}
```

Réponse `200` :

```json
{
  "message": "Profil mis à jour avec succès.",
  "user": {
    "id": 12,
    "email": "jeanne.dupont@example.com",
    "firstName": "Jeanne",
    "lastName": "Dupont",
    "roles": ["ROLE_USER"]
  },
  "requiresReauthentication": true
}
```

`requiresReauthentication` vaut `true` seulement lorsque l'email a changé. Dans ce cas, le JWT existant contient encore l'ancien identifiant : le frontend doit déconnecter l'utilisateur et lui demander de se reconnecter avec le nouvel email.

### Modifier son mot de passe

`PATCH /api/auth/me/password`

```json
{
  "currentPassword": "ancienMotDePasse",
  "newPassword": "nouveauMotDePasse",
  "passwordConfirmation": "nouveauMotDePasse"
}
```

Le nouveau mot de passe doit contenir au moins 8 caractères.

Réponse `200` :

```json
{
  "message": "Mot de passe modifié avec succès."
}
```

Si l'ancien mot de passe est incorrect, la route répond `422` sur le champ `currentPassword`.

## Administration des utilisateurs

### Lister et rechercher

`GET /api/admin/users?page=1&limit=20&search=dupont`

- `page` : entier positif, valeur par défaut `1`.
- `limit` : de `1` à `100`, valeur par défaut `20`.
- `search` : optionnel; recherche sans tenir compte de la casse dans l'email, le prénom et le nom.

Réponse `200` :

```json
{
  "items": [
    {
      "id": 12,
      "email": "jeanne.dupont@example.com",
      "firstName": "Jeanne",
      "lastName": "Dupont",
      "roles": ["ROLE_USER"]
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 20,
    "totalItems": 1,
    "totalPages": 1
  }
}
```

### Lire un utilisateur

`GET /api/admin/users/{id}`

Réponse `200` : même objet utilisateur que dans `items`. Réponse `404` si l'identifiant n'existe pas.

### Créer un utilisateur

`POST /api/admin/users`

```json
{
  "email": "new.user@example.com",
  "firstName": "New",
  "lastName": "User",
  "roles": ["ROLE_USER"],
  "password": "motDePasseInitial",
  "passwordConfirmation": "motDePasseInitial"
}
```

Les champs `email`, `firstName`, `lastName`, `password` et `passwordConfirmation` sont obligatoires. `roles` est optionnel et accepte seulement `ROLE_USER` et `ROLE_ADMIN`. Tout compte possède toujours implicitement `ROLE_USER`.

Réponse `201` :

```json
{
  "message": "Utilisateur créé avec succès.",
  "user": {
    "id": 13,
    "email": "new.user@example.com",
    "firstName": "New",
    "lastName": "User",
    "roles": ["ROLE_USER"]
  }
}
```

### Modifier un utilisateur

`PATCH /api/admin/users/{id}`

Tous les champs sont optionnels :

```json
{
  "email": "updated.user@example.com",
  "firstName": "Updated",
  "lastName": "User",
  "roles": ["ROLE_USER", "ROLE_ADMIN"]
}
```

Réponse `200` :

```json
{
  "message": "Utilisateur mis à jour avec succès.",
  "user": {
    "id": 13,
    "email": "updated.user@example.com",
    "firstName": "Updated",
    "lastName": "User",
    "roles": ["ROLE_ADMIN", "ROLE_USER"]
  }
}
```

### Modifier le mot de passe d'un utilisateur

`PATCH /api/admin/users/{id}/password`

L'administrateur n'a pas besoin de connaître l'ancien mot de passe.

```json
{
  "newPassword": "nouveauMotDePasse",
  "passwordConfirmation": "nouveauMotDePasse"
}
```

Réponse `200` :

```json
{
  "message": "Mot de passe de l’utilisateur modifié avec succès."
}
```

### Supprimer un utilisateur

`DELETE /api/admin/users/{id}`

Réponse `204` sans corps. Réponse `404` si l'identifiant n'existe pas.

## Format des erreurs de validation

Les erreurs métier et de validation répondent avec le statut `422` :

```json
{
  "message": "Les données envoyées sont invalides.",
  "errors": {
    "email": ["Cette adresse email est déjà utilisée."],
    "firstName": ["Le prénom est obligatoire."]
  }
}
```

Autres statuts à gérer côté frontend :

- `400` : JSON invalide ou requête incorrecte;
- `401` : JWT absent, invalide ou expiré;
- `403` : droits administrateur insuffisants;
- `404` : utilisateur introuvable;
- `422` : données invalides.
