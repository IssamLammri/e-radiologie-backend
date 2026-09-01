<?php

namespace App\Service;

use App\Entity\CaseCategory;
use App\Entity\CaseMedia;
use App\Entity\CaseReference;
use App\Entity\ImagingModality;
use App\Entity\RadiologyCase;
use App\Entity\User;
use App\Enum\CaseDifficulty;
use App\Enum\PatientGender;
use App\Enum\RadiologyCaseStatus;
use App\Repository\CaseCategoryRepository;
use App\Repository\ImagingModalityRepository;
use App\Repository\RadiologyCaseRepository;
use App\Repository\UserRepository;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RadiologyCaseInput
{
    public function __construct(
        private ImagingModalityRepository $modalityRepository,
        private CaseCategoryRepository $categoryRepository,
        private UserRepository $userRepository,
        private RadiologyCaseRepository $caseRepository,
        private ValidatorInterface $validator,
    ) {
    }

    /** @return array<string, list<string>> */
    public function apply(RadiologyCase $case, array $data, bool $creating): array
    {
        $errors = [];
        $required = ['title', 'modalityId', 'categoryId', 'difficulty', 'clinicalContext', 'authorId'];
        if ($creating) {
            foreach ($required as $field) {
                if (!array_key_exists($field, $data)) {
                    $errors[$field][] = 'Ce champ est obligatoire.';
                }
            }
        }

        if (array_key_exists('title', $data)) {
            if (!is_string($data['title']) || trim($data['title']) === '') {
                $errors['title'][] = 'Le titre est obligatoire.';
            } else {
                $case->setTitle($data['title']);
                $baseSlug = (new AsciiSlugger('fr'))->slug($data['title'])->lower()->toString();
                $case->setSlug($this->caseRepository->createUniqueSlug($baseSlug, $case->getId()));
            }
        }

        $this->applyRelation($case, $data, 'modalityId', $errors, $this->modalityRepository, 'setModality', ImagingModality::class);
        $this->applyRelation($case, $data, 'categoryId', $errors, $this->categoryRepository, 'setCategory', CaseCategory::class);
        $this->applyRelation($case, $data, 'authorId', $errors, $this->userRepository, 'setAuthor', User::class);

        $this->applyEnum($case, $data, 'difficulty', $errors, CaseDifficulty::class, 'setDifficulty');
        $this->applyEnum($case, $data, 'patientGender', $errors, PatientGender::class, 'setPatientGender');
        $this->applyEnum($case, $data, 'status', $errors, RadiologyCaseStatus::class, 'setStatus');

        if (array_key_exists('patientAge', $data)) {
            if ($data['patientAge'] !== null && (!is_int($data['patientAge']) || $data['patientAge'] < 0)) {
                $errors['patientAge'][] = 'L’âge doit être un entier positif ou nul.';
            } else {
                $case->setPatientAge($data['patientAge']);
            }
        }
        if (array_key_exists('clinicalContext', $data)) {
            if (!is_string($data['clinicalContext']) || trim($data['clinicalContext']) === '') {
                $errors['clinicalContext'][] = 'Le contexte clinique est obligatoire.';
            } else {
                $case->setClinicalContext($data['clinicalContext']);
            }
        }

        foreach (['trainingInstruction', 'trainingPlaceholder', 'expertDescription', 'diagnosis', 'globalDiscussion'] as $field) {
            if (array_key_exists($field, $data)) {
                if ($data[$field] !== null && !is_string($data[$field])) {
                    $errors[$field][] = 'Ce champ doit être une chaîne de caractères ou null.';
                } else {
                    $case->{'set'.ucfirst($field)}($data[$field]);
                }
            }
        }

        if (array_key_exists('publishedAt', $data)) {
            try {
                if ($data['publishedAt'] !== null && !is_string($data['publishedAt'])) {
                    throw new \InvalidArgumentException();
                }
                $case->setPublishedAt($data['publishedAt'] === null ? null : new \DateTimeImmutable($data['publishedAt']));
            } catch (\Exception) {
                $errors['publishedAt'][] = 'La date de publication est invalide.';
            }
        }

        if (array_key_exists('media', $data)) {
            $this->syncMedia($case, $data['media'], $errors);
        }
        if (array_key_exists('references', $data)) {
            $this->syncReferences($case, $data['references'], $errors);
        }

        if ($case->getStatus() === RadiologyCaseStatus::PUBLISHED) {
            if ($case->getPublishedAt() === null) { $case->setStatus(RadiologyCaseStatus::PUBLISHED); }
            if ($case->getMedia()->isEmpty()) { $errors['media'][] = 'Un cas publié doit contenir au moins une image.'; }
            if ($case->getDiagnosis() === null) { $errors['diagnosis'][] = 'Le diagnostic est obligatoire pour publier le cas.'; }
            if ($case->getExpertDescription() === null) { $errors['expertDescription'][] = 'La description experte est obligatoire pour publier le cas.'; }
        }

        foreach ($this->validator->validate($case) as $violation) {
            $errors[$violation->getPropertyPath()][] = $violation->getMessage();
        }
        foreach ($case->getMedia() as $index => $media) {
            foreach ($this->validator->validate($media) as $violation) {
                $errors["media.$index.".$violation->getPropertyPath()][] = $violation->getMessage();
            }
        }
        foreach ($case->getReferences() as $index => $reference) {
            foreach ($this->validator->validate($reference) as $violation) {
                $errors["references.$index.".$violation->getPropertyPath()][] = $violation->getMessage();
            }
        }

        return $errors;
    }

    private function applyRelation(RadiologyCase $case, array $data, string $field, array &$errors, object $repository, string $setter, string $expectedClass): void
    {
        if (!array_key_exists($field, $data)) { return; }
        if (!is_int($data[$field]) && !(is_string($data[$field]) && ctype_digit($data[$field]))) {
            $errors[$field][] = 'L’identifiant est invalide.';
            return;
        }
        $entity = $repository->find((int) $data[$field]);
        if (!$entity instanceof $expectedClass) {
            $errors[$field][] = 'La ressource demandée est introuvable.';
            return;
        }
        $case->{$setter}($entity);
    }

    private function applyEnum(RadiologyCase $case, array $data, string $field, array &$errors, string $enumClass, string $setter): void
    {
        if (!array_key_exists($field, $data)) { return; }
        $value = is_string($data[$field]) ? $enumClass::tryFrom(strtoupper($data[$field])) : null;
        if ($value === null) {
            $errors[$field][] = 'Valeur invalide. Valeurs possibles : '.implode(', ', array_column($enumClass::cases(), 'value')).'.';
            return;
        }
        $case->{$setter}($value);
    }

    private function syncMedia(RadiologyCase $case, mixed $items, array &$errors): void
    {
        if (!is_array($items)) { $errors['media'][] = 'Les médias doivent être un tableau.'; return; }
        foreach ($case->getMedia()->toArray() as $media) { $case->removeMedia($media); }
        $primarySeen = false;
        foreach ($items as $index => $item) {
            if (!is_array($item)) { $errors["media.$index"][] = 'Le média est invalide.'; continue; }
            if (!is_string($item['path'] ?? null) || trim($item['path']) === '') {
                $errors["media.$index.path"][] = 'Le chemin du fichier est obligatoire.';
                continue;
            }
            $media = (new CaseMedia())
                ->setPath($item['path'])
                ->setMediaType(is_string($item['mediaType'] ?? null) ? $item['mediaType'] : 'IMAGE')
                ->setTitle(is_string($item['title'] ?? null) ? $item['title'] : null)
                ->setCaption(is_string($item['caption'] ?? null) ? $item['caption'] : null)
                ->setAltText(is_string($item['altText'] ?? null) ? $item['altText'] : null)
                ->setPosition(is_int($item['position'] ?? null) ? max(0, $item['position']) : $index)
                ->setIsPrimary(($item['isPrimary'] ?? false) === true && !$primarySeen);
            $primarySeen = $primarySeen || $media->isPrimary();
            $case->addMedia($media);
        }
    }

    private function syncReferences(RadiologyCase $case, mixed $items, array &$errors): void
    {
        if (!is_array($items)) { $errors['references'][] = 'Les références doivent être un tableau.'; return; }
        foreach ($case->getReferences()->toArray() as $reference) { $case->removeReference($reference); }
        foreach ($items as $index => $item) {
            if (!is_array($item)) { $errors["references.$index"][] = 'La référence est invalide.'; continue; }
            if (!is_string($item['title'] ?? null) || trim($item['title']) === '') {
                $errors["references.$index.title"][] = 'Le titre est obligatoire.';
                continue;
            }
            $reference = (new CaseReference())
                ->setTitle($item['title'])
                ->setAuthors(is_string($item['authors'] ?? null) ? $item['authors'] : null)
                ->setSource(is_string($item['source'] ?? null) ? $item['source'] : null)
                ->setUrl(is_string($item['url'] ?? null) ? $item['url'] : null)
                ->setDoi(is_string($item['doi'] ?? null) ? $item['doi'] : null)
                ->setPosition(is_int($item['position'] ?? null) ? max(0, $item['position']) : $index);
            $case->addReference($reference);
        }
    }
}
