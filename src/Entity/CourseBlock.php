<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;

#[ORM\Entity]
#[ORM\Table(name: 'course_block')]
class CourseBlock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CourseSection::class, inversedBy: 'blocks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?CourseSection $section = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $contentHtml = '';

    #[ORM\Column(length: 2048, nullable: true)]
    private ?string $imagePath = null;

    #[ORM\Column(length: 24)]
    private string $layout = 'IMAGE_LEFT';

    #[ORM\Column]
    private int $position = 0;

    public function getId(): ?int { return $this->id; }
    public function getSection(): ?CourseSection { return $this->section; }
    public function setSection(?CourseSection $section): static { $this->section = $section; return $this; }
    public function getContentHtml(): string { return $this->contentHtml; }
    public function setContentHtml(string $contentHtml): static { $this->contentHtml = $contentHtml; return $this; }
    public function getImagePath(): ?string { return $this->imagePath; }
    public function setImagePath(?string $imagePath): static { $this->imagePath = $imagePath; return $this; }
    public function getLayout(): string { return $this->layout; }
    public function setLayout(string $layout): static { $this->layout = $layout; return $this; }
    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): static { $this->position = $position; return $this; }
}
