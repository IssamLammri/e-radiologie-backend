<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class EditorialMediaController extends AbstractController
{
    #[Route('/api/admin/media', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function upload(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile || !$file->isValid() || $file->getSize() > 5_000_000) {
            return $this->json(['message' => 'Choisir une image PNG, JPEG ou WebP de 5 Mo maximum.'], 422);
        }
        $info = @getimagesize($file->getPathname());
        $extension = match ($info['mime'] ?? '') { 'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', default => null };
        if ($extension === null || $info[0] > 10000 || $info[1] > 10000 || $file->getMimeType() !== $info['mime']) {
            return $this->json(['message' => 'Format ou dimensions d’image invalides. Les SVG et fichiers exécutables sont refusés.'], 422);
        }
        $name = bin2hex(random_bytes(16)).'.'.$extension;
        $file->move($this->getParameter('kernel.project_dir').'/public/uploads/editorial', $name);
        return $this->json(['path' => '/uploads/editorial/'.$name], 201);
    }
}
