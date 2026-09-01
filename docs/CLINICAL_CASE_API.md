# API des cas cliniques

Le module expose des routes publiques en lecture et un CRUD réservé à `ROLE_ADMIN`.

## Routes publiques

| Méthode | Route | Usage |
|---|---|---|
| `GET` | `/api/cases/recent` | Les 3 derniers cas publiés |
| `GET` | `/api/cases` | Liste paginée des cas publiés |
| `GET` | `/api/cases/{slug}` | Détail d’un cas publié |
| `GET` | `/api/imaging-modalities` | Modalités actives |
| `GET` | `/api/case-categories` | Catégories actives |

La liste accepte `page`, `limit`, `search`, `modality`, `category` et `difficulty` (`BEGINNER`, `INTERMEDIATE`, `ADVANCED`). Un brouillon ou un cas archivé renvoie toujours `404` sur la route publique de détail.

## Routes administrateur

Les ressources suivantes proposent `GET` liste/détail, `POST`, `PATCH` et `DELETE` :

- `/api/admin/radiology-cases`
- `/api/admin/imaging-modalities`
- `/api/admin/case-categories`

Actions supplémentaires :

- `POST /api/admin/radiology-cases/{id}/publish`
- `POST /api/admin/radiology-cases/{id}/archive`

Les médias et références sont synchronisés via les collections `media` et `references` du payload d’un cas. Comme le projet ne disposait pas de service d’upload, `media[].path` accepte le chemin ou l’URL produit par le stockage choisi par le frontend/infrastructure.

## Exemple de création

```json
{
  "title": "Pneumonie lobaire franche aiguë",
  "modalityId": 1,
  "categoryId": 1,
  "difficulty": "BEGINNER",
  "patientGender": "MALE",
  "patientAge": 45,
  "clinicalContext": "Fièvre à 39°C, toux grasse et douleur basithoracique droite.",
  "trainingInstruction": "Examinez le cas et rédigez votre description.",
  "expertDescription": "Opacité alvéolaire systématisée avec bronchogramme aérien.",
  "diagnosis": "Pneumonie lobaire inférieure droite.",
  "globalDiscussion": "Aspect typique d’une pneumonie bactérienne.",
  "authorId": 1,
  "status": "DRAFT",
  "media": [
    {
      "path": "/uploads/cases/pneumonie-face.jpg",
      "mediaType": "IMAGE",
      "title": "Radiographie de face",
      "altText": "Radiographie thoracique de face",
      "position": 0,
      "isPrimary": true
    }
  ],
  "references": [
    {
      "title": "Collège des Enseignants de Radiologie de France",
      "source": "CERF",
      "position": 0
    }
  ]
}
```

Une publication exige au moins un média, un diagnostic et une description experte. Le slug est généré depuis le titre et rendu unique automatiquement.
