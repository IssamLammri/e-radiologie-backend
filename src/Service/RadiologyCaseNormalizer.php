<?php

namespace App\Service;

use App\Entity\CaseCategory;
use App\Entity\CaseMedia;
use App\Entity\CaseReference;
use App\Entity\ImagingModality;
use App\Entity\RadiologyCase;
use App\Entity\User;

final class RadiologyCaseNormalizer
{
    public function normalize(RadiologyCase $case, bool $detail = false): array
    {
        $data = [
            'id' => $case->getId(),
            'title' => $case->getTitle(),
            'slug' => $case->getSlug(),
            'modality' => $this->modality($case->getModality()),
            'category' => $this->category($case->getCategory()),
            'difficulty' => [
                'value' => $case->getDifficulty()->value,
                'label' => $case->getDifficulty()->label(),
            ],
            'author' => $this->author($case->getAuthor()),
            'status' => [
                'value' => $case->getStatus()->value,
                'label' => $case->getStatus()->label(),
            ],
            'publishedAt' => $case->getPublishedAt()?->format(DATE_ATOM),
            'createdAt' => $case->getCreatedAt()?->format(DATE_ATOM),
            'updatedAt' => $case->getUpdatedAt()?->format(DATE_ATOM),
            'primaryMedia' => $case->getPrimaryMedia() === null ? null : $this->media($case->getPrimaryMedia()),
        ];

        if (!$detail) {
            return $data;
        }

        return $data + [
            'patientGender' => [
                'value' => $case->getPatientGender()->value,
                'label' => $case->getPatientGender()->label(),
            ],
            'patientAge' => $case->getPatientAge(),
            'clinicalContext' => $case->getClinicalContext(),
            'trainingInstruction' => $case->getTrainingInstruction(),
            'trainingPlaceholder' => $case->getTrainingPlaceholder(),
            'expertDescription' => $case->getExpertDescription(),
            'diagnosis' => $case->getDiagnosis(),
            'globalDiscussion' => $case->getGlobalDiscussion(),
            'media' => array_map($this->media(...), $case->getMedia()->toArray()),
            'references' => array_map($this->reference(...), $case->getReferences()->toArray()),
        ];
    }

    public function modality(?ImagingModality $modality): ?array
    {
        return $modality === null ? null : [
            'id' => $modality->getId(),
            'name' => $modality->getName(),
            'slug' => $modality->getSlug(),
            'code' => $modality->getCode(),
            'active' => $modality->isActive(),
            'position' => $modality->getPosition(),
        ];
    }

    public function category(?CaseCategory $category): ?array
    {
        return $category === null ? null : [
            'id' => $category->getId(),
            'name' => $category->getName(),
            'slug' => $category->getSlug(),
            'description' => $category->getDescription(),
            'active' => $category->isActive(),
            'position' => $category->getPosition(),
        ];
    }

    private function author(?User $author): ?array
    {
        if ($author === null) { return null; }

        return [
            'id' => $author->getId(),
            'firstName' => $author->getFirstName(),
            'lastName' => $author->getLastName(),
            'displayName' => trim(($author->getFirstName() ?? '').' '.($author->getLastName() ?? '')),
        ];
    }

    private function media(CaseMedia $media): array
    {
        return [
            'id' => $media->getId(),
            'path' => $media->getPath(),
            'mediaType' => $media->getMediaType(),
            'title' => $media->getTitle(),
            'caption' => $media->getCaption(),
            'altText' => $media->getAltText(),
            'position' => $media->getPosition(),
            'isPrimary' => $media->isPrimary(),
        ];
    }

    private function reference(CaseReference $reference): array
    {
        return [
            'id' => $reference->getId(),
            'title' => $reference->getTitle(),
            'authors' => $reference->getAuthors(),
            'source' => $reference->getSource(),
            'url' => $reference->getUrl(),
            'doi' => $reference->getDoi(),
            'position' => $reference->getPosition(),
        ];
    }
}
