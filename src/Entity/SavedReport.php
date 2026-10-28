<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use App\Entity\Traits\TimestampableTrait;

#[ORM\Entity]
#[ORM\Table(name: 'saved_report')]
#[ORM\HasLifecycleCallbacks]
class SavedReport
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $contentHtml = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $conclusionHtml = '';

    #[ORM\Column(type: Types::JSON)]
    private array $personalization = [];

    public function getId(): ?int { return $this->id; }
    public function getOwner(): ?User { return $this->owner; }
    public function setOwner(?User $owner): static { $this->owner = $owner; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }
    public function getContentHtml(): string { return $this->contentHtml; }
    public function setContentHtml(string $contentHtml): static { $this->contentHtml = $contentHtml; return $this; }
    public function getConclusionHtml(): string { return $this->conclusionHtml; }
    public function setConclusionHtml(string $conclusionHtml): static { $this->conclusionHtml = $conclusionHtml; return $this; }
    public function getPersonalization(): array { return $this->personalization; }
    public function setPersonalization(array $personalization): static { $this->personalization = $personalization; return $this; }
}
