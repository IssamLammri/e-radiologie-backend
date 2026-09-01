<?php

namespace App\Entity;

use App\Entity\Traits\TimestampableTrait;
use App\Repository\CaseMediaRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CaseMediaRepository::class)]
#[ORM\Table(name: 'case_media')]
#[ORM\UniqueConstraint(name: 'uniq_case_primary_media', columns: ['radiology_case_id'], options: ['where' => '(is_primary = true)'])]
#[ORM\HasLifecycleCallbacks]
class CaseMedia
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'media')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?RadiologyCase $radiologyCase = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 2048)]
    #[ORM\Column(length: 2048)]
    private ?string $path = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    #[ORM\Column(length: 80, options: ['default' => 'IMAGE'])]
    private string $mediaType = 'IMAGE';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $caption = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $altText = null;

    #[Assert\PositiveOrZero]
    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column(options: ['default' => false])]
    private bool $isPrimary = false;

    public function getId(): ?int { return $this->id; }
    public function getRadiologyCase(): ?RadiologyCase { return $this->radiologyCase; }
    public function setRadiologyCase(?RadiologyCase $radiologyCase): static { $this->radiologyCase = $radiologyCase; return $this; }
    public function getPath(): ?string { return $this->path; }
    public function setPath(string $path): static { $this->path = trim($path); return $this; }
    public function getMediaType(): string { return $this->mediaType; }
    public function setMediaType(string $mediaType): static { $this->mediaType = strtoupper(trim($mediaType)); return $this; }
    public function getTitle(): ?string { return $this->title; }
    public function setTitle(?string $title): static { $this->title = self::trimNullable($title); return $this; }
    public function getCaption(): ?string { return $this->caption; }
    public function setCaption(?string $caption): static { $this->caption = self::trimNullable($caption); return $this; }
    public function getAltText(): ?string { return $this->altText; }
    public function setAltText(?string $altText): static { $this->altText = self::trimNullable($altText); return $this; }
    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): static { $this->position = $position; return $this; }
    public function isPrimary(): bool { return $this->isPrimary; }

    public function setIsPrimary(bool $isPrimary): static
    {
        $this->isPrimary = $isPrimary;
        if ($isPrimary && $this->radiologyCase !== null) {
            foreach ($this->radiologyCase->getMedia() as $media) {
                if ($media !== $this) {
                    $media->isPrimary = false;
                }
            }
        }
        return $this;
    }

    private static function trimNullable(?string $value): ?string
    {
        if ($value === null) { return null; }
        $value = trim($value);
        return $value === '' ? null : $value;
    }
}
