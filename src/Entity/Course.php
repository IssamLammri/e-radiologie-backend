<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use App\Entity\Traits\TimestampableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
#[ORM\Table(name: 'course')]
#[ORM\HasLifecycleCallbacks]
class Course
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Specialty::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?Specialty $specialty = null;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(length: 2048)]
    private string $imagePath = '';

    #[ORM\Column(length: 255)]
    private string $authorName = '';

    #[ORM\Column(length: 255)]
    private string $institution = '';

    /** @var Collection<int, CourseSection> */
    #[ORM\OneToMany(mappedBy: 'course', targetEntity: CourseSection::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $sections;

    public function __construct()
    {
        $this->sections = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getSpecialty(): ?Specialty { return $this->specialty; }
    public function setSpecialty(?Specialty $specialty): static { $this->specialty = $specialty; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }
    public function getImagePath(): string { return $this->imagePath; }
    public function setImagePath(string $imagePath): static { $this->imagePath = $imagePath; return $this; }
    public function getAuthorName(): string { return $this->authorName; }
    public function setAuthorName(string $authorName): static { $this->authorName = $authorName; return $this; }
    public function getInstitution(): string { return $this->institution; }
    public function setInstitution(string $institution): static { $this->institution = $institution; return $this; }
    /** @return Collection<int, CourseSection> */
    public function getSections(): Collection { return $this->sections; }
    public function addSection(CourseSection $item): static
    {
        if (!$this->sections->contains($item)) { $this->sections->add($item); $item->setCourse($this); }
        return $this;
    }
    public function removeSection(CourseSection $item): static { $this->sections->removeElement($item); return $this; }
}
