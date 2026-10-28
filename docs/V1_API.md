# API éditoriale et comptes rendus V1

Cette documentation complète les API de cas cliniques et d'utilisateurs existantes.
Authentification : JWT existant, en-tête `Authorization: Bearer <token>`.

## Ressources éditoriales

| Ressource | Entité | Champs principaux |
| --- | --- | --- |
| `specialties` | `Specialty` | `name`, `position` |
| `classifications` | `MedicalClassification`, `EvaluationRule` | `specialtyId`, `type`, `name`, `description`, `indications`, `rules` |
| `courses` | `Course`, `CourseSection`, `CourseBlock` | `specialtyId`, `title`, `imagePath`, `authorName`, `institution`, `sections` |
| `dems-exercises` | `DemsExercise` | `name`, `maskedName`, `imagePath`, `clinicalPresentation`, cinq références |
| `report-exams` | `ReportExam` | `modalityId`, `name` |
| `report-templates` | `ReportTemplate` | `examId`, `category`, `label`, `contentHtml`, `conclusionHtml` |

Pour chacune des ressources :

- `GET /api/content/{resource}` : liste consultable par le site client.
- `GET /api/content/{resource}/{id}` : détail consultable.
- `GET /api/admin/content/{resource}` : liste administrateur.
- `GET /api/admin/content/{resource}/{id}` : détail administrateur.
- `POST /api/admin/content/{resource}` : création, réponse 201.
- `PATCH /api/admin/content/{resource}/{id}` : modification.
- `DELETE /api/admin/content/{resource}/{id}` : suppression, réponse 204.

Toutes les routes `/api/admin` exigent `ROLE_ADMIN`. Une dépendance empêchant
une suppression produit un statut 409 avec un message explicite. Les règles,
sections et blocs sont supprimés avec leur contenu parent. Les spécialités,
modalités et examens utilisés doivent être libérés de leurs dépendances avant
leur suppression.

Les listes acceptent `search`, `page` et `limit` (100 par défaut, 200 maximum).
Les filtres `specialty`, `modality` et `exam` sont disponibles sur leurs ressources
respectives. Les réponses contiennent `items` et `pagination`.
Les spécialités incluent `classificationCount` et `courseCount`, même à zéro.

Les modalités restent gérées par les routes existantes
`/api/admin/imaging-modalities` ; aucune deuxième entité de modalité n'est créée.

## Classifications

`rules` est une liste ordonnée de `{ grade, semiology, clinicalDescription }`.
La position est déduite de l'ordre de la liste. Une modification de `rules`
remplace cette liste ; les autres champs non fournis ne sont pas changés.

`calculatorKey` est une information de compatibilité en lecture seule pour les
sept calculateurs déjà présents dans le prototype. Aucun algorithme nouveau,
éditeur de formule ou nouveau calculateur n'est introduit.

## Cours

Chaque section contient `title`, `blocks` et `children`. Exemple structurel :

```json
{
  "title": "Introduction",
  "blocks": [
    {
      "contentHtml": "<p>Texte éditorial fourni par le rédacteur.</p>",
      "imagePath": null,
      "layout": "IMAGE_LEFT"
    }
  ],
  "children": [{ "title": "Sous-titre", "blocks": [], "children": [] }]
}
```

Les quatre dispositions sont `IMAGE_LEFT`, `IMAGE_RIGHT`, `IMAGE_TOP` et
`IMAGE_BOTTOM`. Chaque bloc doit posséder un texte ou une image. Un bloc peut
avoir les deux. L'ordre des tableaux définit l'ordre d'affichage.
Le HTML est assaini côté serveur par Symfony HTML Sanitizer avant stockage.
La profondeur et le volume des contenus sont bornés pour la validation technique.

## Médias

`POST /api/admin/media` accepte un formulaire multipart avec le champ `file`.
Seuls les PNG, JPEG et WebP réels, de 5 Mo maximum, sont acceptés. MIME,
dimensions et contenu image sont contrôlés ; les noms sont générés aléatoirement.
La réponse 201 contient `path`, utilisable dans les champs `imagePath`.
Les chemins locaux doivent correspondre à une image existante importée.
Les URL HTTP(S) sont également acceptées ; le serveur ne télécharge pas ces URL.

Les logos personnels d'un compte rendu sont lus localement par le navigateur.
Ils ne passent pas par cet endpoint public de médias éditoriaux.

## Planchages DEMS

Les cinq champs de référence sont `identification`, `analyticStudy`,
`syntheticStudy`, `differentialDiagnosis` et `management`. L'administration les
envoie au niveau racine du document ; le détail administrateur les retourne
regroupés dans `answers`.

La liste et le détail client ne contiennent jamais ces références.
`POST /api/content/dems-exercises/{id}/results` les retourne dans `answers`
après l'action « Afficher les résultats ». Les réponses de l'apprenant ne sont
pas envoyées au serveur et aucune notation n'est effectuée.

## Comptes rendus privés

Entité dédiée : `SavedReport`, liée uniquement à son propriétaire `User`.
Elle conserve un instantané du contenu et des champs de personnalisation ; une
suppression du modèle éditorial n'altère pas ce document personnel.

- `GET /api/me/reports` : liste du seul utilisateur courant, sans champs patient.
- `GET /api/me/reports/{id}` : détail de son document.
- `POST /api/me/reports` : enregistrement volontaire.
- `PATCH /api/me/reports/{id}` : enregistrement volontaire des modifications.
- `DELETE /api/me/reports/{id}` : suppression de son document.

Les mutations POST/PATCH attendent un instantané complet :
`saveExplicitly: true`, `title`, `contentHtml`, `conclusionHtml`, `personalization`.
Le propriétaire est déterminé par le JWT, jamais par un identifiant envoyé par
le client. L'absence du marqueur explicite produit un statut 422. Un compte rendu
d'un autre utilisateur retourne 404 en lecture, modification et suppression,
y compris pour un administrateur de contenus.

Champs autorisés dans `personalization` : `clinicName`, `clinicAddress`,
`clinicContact`, `patientName`, `patientBirthDate`, `patientAge`, `indication`,
`practitioner`, `documentDate`, `logoChoice`, `logoData`. Les dates utilisent
`YYYY-MM-DD`. Les données d'image du logo sont bornées et validées.

Ces routes renvoient `Cache-Control: no-store, private`. Les exceptions de
sauvegarde sont transformées en messages génériques sans journaliser le corps
ni les informations patient. Aucun endpoint d'administration des comptes rendus
personnels n'est exposé. L'impression, la copie et l'export s'effectuent dans le
navigateur ; aucune génération de PDF n'est enregistrée sur le serveur.

## Migration et reprise des prototypes

`Version20261028092120` crée les dix nouvelles tables et reprend les données déjà
présentes : 12 spécialités, 14 classifications et leurs règles, 18 examens et
36 modèles. Les sept calculateurs existants restent dans le client, à l'identique.
Le fichier `resources/legacy-editorial-content.json` est un instantané utilisé
une seule fois par cette migration, pas un référentiel utilisé à l'exécution.
Aucun cours ou planchage médical n'est fabriqué pour remplir les écrans.

## Vérifications

```bash
docker compose exec -T php composer test
docker compose exec -T php composer test:integration
docker compose exec -T php composer test:v1
docker compose exec -T php php bin/console doctrine:schema:validate
docker compose exec -T php composer audit
```

Les tests V1 créent des contenus synthétiques via HTTP puis les suppriment.
Ils ne suppriment pas les données préexistantes. Ils utilisent les comptes de
test locaux ; ils sont destinés à la configuration Docker de développement.
