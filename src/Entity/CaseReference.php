<?php

namespace App\Entity;

use App\Entity\Traits\TimestampableTrait;
use App\Repository\CaseReferenceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CaseReferenceRepository::class)]
#[ORM\HasLifecycleCallbacks]
class CaseReference
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'references')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?RadiologyCase $radiologyCase = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 500)]
    #[ORM\Column(length: 500)]
    private ?string $title = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $authors = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $source = null;

    #[Assert\Url]
    #[ORM\Column(length: 2048, nullable: true)]
    private ?string $url = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $doi = null;

    #[Assert\PositiveOrZero]
    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    public function getId(): ?int { return $this->id; }
    public function getRadiologyCase(): ?RadiologyCase { return $this->radiologyCase; }
    public function setRadiologyCase(?RadiologyCase $radiologyCase): static { $this->radiologyCase = $radiologyCase; return $this; }
    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): static { $this->title = trim($title); return $this; }
    public function getAuthors(): ?string { return $this->authors; }
    public function setAuthors(?string $authors): static { $this->authors = self::trimNullable($authors); return $this; }
    public function getSource(): ?string { return $this->source; }
    public function setSource(?string $source): static { $this->source = self::trimNullable($source); return $this; }
    public function getUrl(): ?string { return $this->url; }
    public function setUrl(?string $url): static { $this->url = self::trimNullable($url); return $this; }
    public function getDoi(): ?string { return $this->doi; }
    public function setDoi(?string $doi): static { $this->doi = self::trimNullable($doi); return $this; }
    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): static { $this->position = $position; return $this; }

    private static function trimNullable(?string $value): ?string
    {
        if ($value === null) { return null; }
        $value = trim($value);
        return $value === '' ? null : $value;
    }
}
