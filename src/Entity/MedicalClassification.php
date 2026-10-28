<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use App\Entity\Traits\TimestampableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
#[ORM\Table(name: 'medical_classification')]
#[ORM\HasLifecycleCallbacks]
class MedicalClassification
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Specialty::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?Specialty $specialty = null;

    #[ORM\Column(length: 120)]
    private string $type = '';

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $description = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $indications = '';

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $calculatorKey = null;

    /** @var Collection<int, EvaluationRule> */
    #[ORM\OneToMany(mappedBy: 'classification', targetEntity: EvaluationRule::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $rules;

    public function __construct()
    {
        $this->rules = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getSpecialty(): ?Specialty { return $this->specialty; }
    public function setSpecialty(?Specialty $specialty): static { $this->specialty = $specialty; return $this; }
    public function getType(): string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    public function getDescription(): string { return $this->description; }
    public function setDescription(string $description): static { $this->description = $description; return $this; }
    public function getIndications(): string { return $this->indications; }
    public function setIndications(string $indications): static { $this->indications = $indications; return $this; }
    public function getCalculatorKey(): ?string { return $this->calculatorKey; }
    public function setCalculatorKey(?string $calculatorKey): static { $this->calculatorKey = $calculatorKey; return $this; }
    /** @return Collection<int, EvaluationRule> */
    public function getRules(): Collection { return $this->rules; }
    public function addRule(EvaluationRule $item): static
    {
        if (!$this->rules->contains($item)) { $this->rules->add($item); $item->setClassification($this); }
        return $this;
    }
    public function removeRule(EvaluationRule $item): static { $this->rules->removeElement($item); return $this; }
}
