<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use App\Entity\Traits\TimestampableTrait;

#[ORM\Entity]
#[ORM\Table(name: 'report_exam')]
#[ORM\HasLifecycleCallbacks]
class ReportExam
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ImagingModality::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?ImagingModality $modality = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    public function getId(): ?int { return $this->id; }
    public function getModality(): ?ImagingModality { return $this->modality; }
    public function setModality(?ImagingModality $modality): static { $this->modality = $modality; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
}
