<?php

namespace App\Entity;

use App\Entity\Traits\TimestampableTrait;
use App\Repository\ImagingModalityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ImagingModalityRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ImagingModality
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    #[ORM\Column(length: 120)]
    private ?string $name = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 140)]
    #[ORM\Column(length: 140, unique: true)]
    private ?string $slug = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    #[ORM\Column(length: 50, unique: true)]
    private ?string $code = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    #[Assert\PositiveOrZero]
    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    /** @var Collection<int, RadiologyCase> */
    #[ORM\OneToMany(mappedBy: 'modality', targetEntity: RadiologyCase::class)]
    private Collection $radiologyCases;

    public function __construct()
    {
        $this->radiologyCases = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = trim($name); return $this; }
    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(string $slug): static { $this->slug = trim($slug); return $this; }
    public function getCode(): ?string { return $this->code; }
    public function setCode(string $code): static { $this->code = strtoupper(trim($code)); return $this; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): static { $this->active = $active; return $this; }
    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): static { $this->position = $position; return $this; }
}
