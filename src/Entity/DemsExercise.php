<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use App\Entity\Traits\TimestampableTrait;

#[ORM\Entity]
#[ORM\Table(name: 'dems_exercise')]
#[ORM\HasLifecycleCallbacks]
class DemsExercise
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(length: 255)]
    private string $maskedName = '';

    #[ORM\Column(length: 2048)]
    private string $imagePath = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $clinicalPresentation = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $identification = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $analyticStudy = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $syntheticStudy = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $differentialDiagnosis = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $management = '';

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    public function getMaskedName(): string { return $this->maskedName; }
    public function setMaskedName(string $maskedName): static { $this->maskedName = $maskedName; return $this; }
    public function getImagePath(): string { return $this->imagePath; }
    public function setImagePath(string $imagePath): static { $this->imagePath = $imagePath; return $this; }
    public function getClinicalPresentation(): string { return $this->clinicalPresentation; }
    public function setClinicalPresentation(string $clinicalPresentation): static { $this->clinicalPresentation = $clinicalPresentation; return $this; }
    public function getIdentification(): string { return $this->identification; }
    public function setIdentification(string $identification): static { $this->identification = $identification; return $this; }
    public function getAnalyticStudy(): string { return $this->analyticStudy; }
    public function setAnalyticStudy(string $analyticStudy): static { $this->analyticStudy = $analyticStudy; return $this; }
    public function getSyntheticStudy(): string { return $this->syntheticStudy; }
    public function setSyntheticStudy(string $syntheticStudy): static { $this->syntheticStudy = $syntheticStudy; return $this; }
    public function getDifferentialDiagnosis(): string { return $this->differentialDiagnosis; }
    public function setDifferentialDiagnosis(string $differentialDiagnosis): static { $this->differentialDiagnosis = $differentialDiagnosis; return $this; }
    public function getManagement(): string { return $this->management; }
    public function setManagement(string $management): static { $this->management = $management; return $this; }
}
