<?php

namespace App\Controller;

use App\Entity\CaseCategory;
use App\Entity\ImagingModality;
use App\Repository\CaseCategoryRepository;
use App\Repository\ImagingModalityRepository;
use App\Service\RadiologyCaseNormalizer;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/admin')]
#[IsGranted('ROLE_ADMIN')]
final class AdminRadiologyReferenceController extends AbstractController
{
    #[Route('/imaging-modalities', name: 'api_admin_modalities_list', methods: ['GET'])]
    public function modalities(ImagingModalityRepository $repository, RadiologyCaseNormalizer $normalizer): JsonResponse
    {
        return $this->json(['items' => array_map($normalizer->modality(...), $repository->findBy([], ['position' => 'ASC', 'name' => 'ASC']))]);
    }

    #[Route('/imaging-modalities/{id<\d+>}', name: 'api_admin_modalities_show', methods: ['GET'])]
    public function modality(ImagingModality $modality, RadiologyCaseNormalizer $normalizer): JsonResponse
    {
        return $this->json($normalizer->modality($modality));
    }

    #[Route('/imaging-modalities', name: 'api_admin_modalities_create', methods: ['POST'])]
    public function createModality(Request $request, ImagingModalityRepository $repository, EntityManagerInterface $entityManager, ValidatorInterface $validator, RadiologyCaseNormalizer $normalizer): JsonResponse
    {
        $modality = new ImagingModality();
        $errors = $this->applyModality($modality, $request->toArray(), true, $repository, $validator);
        if ($errors !== []) { return $this->validationError($errors); }
        $entityManager->persist($modality);
        $entityManager->flush();
        return $this->json($normalizer->modality($modality), 201);
    }

    #[Route('/imaging-modalities/{id<\d+>}', name: 'api_admin_modalities_update', methods: ['PATCH'])]
    public function updateModality(ImagingModality $modality, Request $request, ImagingModalityRepository $repository, EntityManagerInterface $entityManager, ValidatorInterface $validator, RadiologyCaseNormalizer $normalizer): JsonResponse
    {
        $errors = $this->applyModality($modality, $request->toArray(), false, $repository, $validator);
        if ($errors !== []) { return $this->validationError($errors); }
        $entityManager->flush();
        return $this->json($normalizer->modality($modality));
    }

    #[Route('/imaging-modalities/{id<\d+>}', name: 'api_admin_modalities_delete', methods: ['DELETE'])]
    public function deleteModality(ImagingModality $modality, EntityManagerInterface $entityManager): JsonResponse
    {
        return $this->safeDelete($modality, $entityManager);
    }

    #[Route('/case-categories', name: 'api_admin_categories_list', methods: ['GET'])]
    public function categories(CaseCategoryRepository $repository, RadiologyCaseNormalizer $normalizer): JsonResponse
    {
        return $this->json(['items' => array_map($normalizer->category(...), $repository->findBy([], ['position' => 'ASC', 'name' => 'ASC']))]);
    }

    #[Route('/case-categories/{id<\d+>}', name: 'api_admin_categories_show', methods: ['GET'])]
    public function category(CaseCategory $category, RadiologyCaseNormalizer $normalizer): JsonResponse
    {
        return $this->json($normalizer->category($category));
    }

    #[Route('/case-categories', name: 'api_admin_categories_create', methods: ['POST'])]
    public function createCategory(Request $request, CaseCategoryRepository $repository, EntityManagerInterface $entityManager, ValidatorInterface $validator, RadiologyCaseNormalizer $normalizer): JsonResponse
    {
        $category = new CaseCategory();
        $errors = $this->applyCategory($category, $request->toArray(), true, $repository, $validator);
        if ($errors !== []) { return $this->validationError($errors); }
        $entityManager->persist($category);
        $entityManager->flush();
        return $this->json($normalizer->category($category), 201);
    }

    #[Route('/case-categories/{id<\d+>}', name: 'api_admin_categories_update', methods: ['PATCH'])]
    public function updateCategory(CaseCategory $category, Request $request, CaseCategoryRepository $repository, EntityManagerInterface $entityManager, ValidatorInterface $validator, RadiologyCaseNormalizer $normalizer): JsonResponse
    {
        $errors = $this->applyCategory($category, $request->toArray(), false, $repository, $validator);
        if ($errors !== []) { return $this->validationError($errors); }
        $entityManager->flush();
        return $this->json($normalizer->category($category));
    }

    #[Route('/case-categories/{id<\d+>}', name: 'api_admin_categories_delete', methods: ['DELETE'])]
    public function deleteCategory(CaseCategory $category, EntityManagerInterface $entityManager): JsonResponse
    {
        return $this->safeDelete($category, $entityManager);
    }

    private function applyModality(ImagingModality $entity, array $data, bool $creating, ImagingModalityRepository $repository, ValidatorInterface $validator): array
    {
        $errors = $this->applyCommon($entity, $data, $creating);
        if ($creating && !array_key_exists('code', $data)) { $errors['code'][] = 'Ce champ est obligatoire.'; }
        if (array_key_exists('code', $data)) {
            if (!is_string($data['code']) || trim($data['code']) === '') {
                $errors['code'][] = 'Le code est obligatoire.';
            } else {
                $entity->setCode($data['code']);
                $existing = $repository->findOneBy(['code' => $entity->getCode()]);
                if ($existing !== null && $existing->getId() !== $entity->getId()) { $errors['code'][] = 'Ce code est déjà utilisé.'; }
            }
        }
        return $this->entityErrors($entity, $errors, $repository, $validator);
    }

    private function applyCategory(CaseCategory $entity, array $data, bool $creating, CaseCategoryRepository $repository, ValidatorInterface $validator): array
    {
        $errors = $this->applyCommon($entity, $data, $creating);
        if (array_key_exists('description', $data)) {
            if ($data['description'] !== null && !is_string($data['description'])) { $errors['description'][] = 'La description est invalide.'; }
            else { $entity->setDescription($data['description']); }
        }
        return $this->entityErrors($entity, $errors, $repository, $validator);
    }

    private function applyCommon(ImagingModality|CaseCategory $entity, array $data, bool $creating): array
    {
        $errors = [];
        if ($creating && !array_key_exists('name', $data)) { $errors['name'][] = 'Ce champ est obligatoire.'; }
        if (array_key_exists('name', $data)) {
            if (!is_string($data['name']) || trim($data['name']) === '') { $errors['name'][] = 'Le nom est obligatoire.'; }
            else {
                $entity->setName($data['name']);
                $entity->setSlug((new AsciiSlugger('fr'))->slug($data['name'])->lower()->toString());
            }
        }
        if (array_key_exists('active', $data)) {
            if (!is_bool($data['active'])) { $errors['active'][] = 'La valeur doit être un booléen.'; }
            else { $entity->setActive($data['active']); }
        }
        if (array_key_exists('position', $data)) {
            if (!is_int($data['position']) || $data['position'] < 0) { $errors['position'][] = 'La position doit être un entier positif ou nul.'; }
            else { $entity->setPosition($data['position']); }
        }
        return $errors;
    }

    private function entityErrors(object $entity, array $errors, object $repository, ValidatorInterface $validator): array
    {
        if (method_exists($entity, 'getSlug') && $entity->getSlug() !== null) {
            $existing = $repository->findOneBy(['slug' => $entity->getSlug()]);
            if ($existing !== null && $existing->getId() !== $entity->getId()) { $errors['name'][] = 'Un élément avec ce nom existe déjà.'; }
        }
        foreach ($validator->validate($entity) as $violation) {
            $errors[$violation->getPropertyPath()][] = $violation->getMessage();
        }
        return $errors;
    }

    private function safeDelete(object $entity, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $entityManager->remove($entity);
            $entityManager->flush();
        } catch (ForeignKeyConstraintViolationException) {
            return $this->json(['message' => 'Cette ressource est utilisée par un cas clinique et ne peut pas être supprimée.'], 409);
        }
        return new JsonResponse(null, 204);
    }

    private function validationError(array $errors): JsonResponse
    {
        return $this->json(['message' => 'Les données envoyées sont invalides.', 'errors' => $errors], 422);
    }
}
