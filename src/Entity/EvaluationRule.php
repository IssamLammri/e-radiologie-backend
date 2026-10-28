<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;

#[ORM\Entity]
#[ORM\Table(name: 'evaluation_rule')]
class EvaluationRule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: MedicalClassification::class, inversedBy: 'rules')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?MedicalClassification $classification = null;

    #[ORM\Column(length: 255)]
    private string $grade = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $semiology = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $clinicalDescription = '';

    #[ORM\Column]
    private int $position = 0;

    public function getId(): ?int { return $this->id; }
    public function getClassification(): ?MedicalClassification { return $this->classification; }
    public function setClassification(?MedicalClassification $classification): static { $this->classification = $classification; return $this; }
    public function getGrade(): string { return $this->grade; }
    public function setGrade(string $grade): static { $this->grade = $grade; return $this; }
    public function getSemiology(): string { return $this->semiology; }
    public function setSemiology(string $semiology): static { $this->semiology = $semiology; return $this; }
    public function getClinicalDescription(): string { return $this->clinicalDescription; }
    public function setClinicalDescription(string $clinicalDescription): static { $this->clinicalDescription = $clinicalDescription; return $this; }
    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): static { $this->position = $position; return $this; }
}
