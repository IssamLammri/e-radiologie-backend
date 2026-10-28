<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
#[ORM\Table(name: 'course_section')]
class CourseSection
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Course::class, inversedBy: 'sections')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Course $course = null;

    #[ORM\ManyToOne(targetEntity: CourseSection::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?CourseSection $parent = null;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column]
    private int $position = 0;

    /** @var Collection<int, CourseBlock> */
    #[ORM\OneToMany(mappedBy: 'section', targetEntity: CourseBlock::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $blocks;

    public function __construct()
    {
        $this->blocks = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getCourse(): ?Course { return $this->course; }
    public function setCourse(?Course $course): static { $this->course = $course; return $this; }
    public function getParent(): ?CourseSection { return $this->parent; }
    public function setParent(?CourseSection $parent): static { $this->parent = $parent; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }
    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): static { $this->position = $position; return $this; }
    /** @return Collection<int, CourseBlock> */
    public function getBlocks(): Collection { return $this->blocks; }
    public function addBlock(CourseBlock $item): static
    {
        if (!$this->blocks->contains($item)) { $this->blocks->add($item); $item->setSection($this); }
        return $this;
    }
    public function removeBlock(CourseBlock $item): static { $this->blocks->removeElement($item); return $this; }
}
