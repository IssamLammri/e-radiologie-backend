<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\{SavedReport, User};
use App\Service\EditorialContent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/me/reports')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class SavedReportController extends AbstractController
{
    private const PERSONAL_FIELDS = ['clinicName', 'clinicAddress', 'clinicContact', 'patientName', 'patientBirthDate', 'patientAge', 'indication', 'practitioner', 'documentDate', 'logoChoice', 'logoData'];

    #[Route('', methods: ['GET'])]
    public function list(EntityManagerInterface $em): JsonResponse
    {
        $reports = $em->getRepository(SavedReport::class)->findBy(['owner' => $this->getUser()], ['updatedAt' => 'DESC']);
        return $this->response(['items' => array_map(fn ($r) => $this->normalize($r, false), $reports)]);
    }

    #[Route('/{id<\d+>}', methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $em): JsonResponse
    {
        $report = $this->owned($id, $em);
        return $report ? $this->response($this->normalize($report)) : $this->response(['message' => 'Compte rendu introuvable.'], 404);
    }

    #[Route('', methods: ['POST'])]
    #[Route('/{id<\d+>}', methods: ['PATCH'])]
    public function save(Request $request, EntityManagerInterface $em, EditorialContent $content, ?int $id = null): JsonResponse
    {
        // No patient data is ever passed to an exception or technical logger.
        try {
            if (strlen($request->getContent()) > 3_000_000) { return $this->response(['message' => 'Document trop volumineux.'], 422); }
            $data = $request->toArray();
            if (($data['saveExplicitly'] ?? false) !== true) { return $this->response(['message' => 'Une action explicite d’enregistrement est obligatoire.'], 422); }
            $report = $id === null ? new SavedReport() : $this->owned($id, $em);
            if (!$report) { return $this->response(['message' => 'Compte rendu introuvable.'], 404); }
            foreach (['title' => 255, 'contentHtml' => 200000, 'conclusionHtml' => 200000] as $field => $max) {
                if (!is_string($data[$field] ?? null) || mb_strlen($data[$field]) > $max || ($field === 'title' && trim($data[$field]) === '')) {
                    return $this->response(['message' => 'Titre ou contenu du document invalide.'], 422);
                }
            }
            $personal = $data['personalization'] ?? [];
            if (!is_array($personal) || array_diff(array_keys($personal), self::PERSONAL_FIELDS)) { return $this->response(['message' => 'Champs de personnalisation invalides.'], 422); }
            foreach ($personal as $field => $value) {
                if (!is_string($value) || mb_strlen($value) > ($field === 'logoData' ? 2_000_000 : 2000)) { return $this->response(['message' => 'Personnalisation invalide.'], 422); }
            }
            foreach (['patientBirthDate', 'documentDate'] as $field) {
                if (!empty($personal[$field])) {
                    $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $personal[$field]);
                    if (!$date || $date->format('Y-m-d') !== $personal[$field]) { return $this->response(['message' => 'Date de personnalisation invalide.'], 422); }
                }
            }
            if (!empty($personal['patientAge']) && !ctype_digit($personal['patientAge'])) { return $this->response(['message' => 'L’âge doit être un entier positif ou nul.'], 422); }
            if (isset($personal['logoChoice']) && !in_array($personal['logoChoice'], ['cross_blue', 'cross_green', 'ring', 'none', 'custom'], true)) { return $this->response(['message' => 'Choix du logo invalide.'], 422); }
            if (!empty($personal['logoData'])) {
                if (!preg_match('~^data:image/(png|jpeg|webp);base64,([A-Za-z0-9+/=]+)$~D', $personal['logoData'], $match)) { return $this->response(['message' => 'Logo invalide.'], 422); }
                $bytes = base64_decode($match[2], true);
                $info = $bytes === false ? false : @getimagesizefromstring($bytes);
                if (!$info || $info['mime'] !== 'image/'.$match[1] || $info[0] > 10000 || $info[1] > 10000) { return $this->response(['message' => 'Logo invalide.'], 422); }
            }
            /** @var User $owner */
            $owner = $this->getUser();
            $report->setOwner($owner)->setTitle(trim($data['title']))->setContentHtml($content->cleanHtml($data['contentHtml']))->setConclusionHtml($content->cleanHtml($data['conclusionHtml']))->setPersonalization($personal);
            $em->persist($report);
            $em->flush();
            return $this->response($this->normalize($report), $id === null ? 201 : 200);
        } catch (\Throwable) {
            return $this->response(['message' => 'Le compte rendu n’a pas pu être enregistré.'], 400);
        }
    }

    #[Route('/{id<\d+>}', methods: ['DELETE'])]
    public function delete(int $id, EntityManagerInterface $em): JsonResponse
    {
        try {
            $report = $this->owned($id, $em);
            if (!$report) { return $this->response(['message' => 'Compte rendu introuvable.'], 404); }
            $em->remove($report);
            $em->flush();
            return $this->response(null, 204);
        } catch (\Throwable) { return $this->response(['message' => 'La suppression a échoué.'], 400); }
    }

    private function owned(int $id, EntityManagerInterface $em): ?SavedReport
    {
        return $em->getRepository(SavedReport::class)->findOneBy(['id' => $id, 'owner' => $this->getUser()]);
    }

    private function normalize(SavedReport $report, bool $detail = true): array
    {
        $data = ['id' => $report->getId(), 'title' => $report->getTitle(), 'updatedAt' => $report->getUpdatedAt()?->format(DATE_ATOM)];
        if ($detail) { $data += ['contentHtml' => $report->getContentHtml(), 'conclusionHtml' => $report->getConclusionHtml(), 'personalization' => $report->getPersonalization()]; }
        return $data;
    }

    private function response(mixed $data, int $status = 200): JsonResponse
    {
        return $this->json($data, $status, ['Cache-Control' => 'no-store, private', 'Pragma' => 'no-cache', 'X-Robots-Tag' => 'noindex']);
    }
}
