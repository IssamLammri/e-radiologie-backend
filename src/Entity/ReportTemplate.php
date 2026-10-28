<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use App\Entity\Traits\TimestampableTrait;

#[ORM\Entity]
#[ORM\Table(name: 'report_template')]
#[ORM\HasLifecycleCallbacks]
class ReportTemplate
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ReportExam::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?ReportExam $exam = null;

    #[ORM\Column(length: 16)]
    private string $category = 'NORMAL';

    #[ORM\Column(length: 255)]
    private string $label = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $contentHtml = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $conclusionHtml = '';

    public function getId(): ?int { return $this->id; }
    public function getExam(): ?ReportExam { return $this->exam; }
    public function setExam(?ReportExam $exam): static { $this->exam = $exam; return $this; }
    public function getCategory(): string { return $this->category; }
    public function setCategory(string $category): static { $this->category = $category; return $this; }
    public function getLabel(): string { return $this->label; }
    public function setLabel(string $label): static { $this->label = $label; return $this; }
    public function getContentHtml(): string { return $this->contentHtml; }
    public function setContentHtml(string $contentHtml): static { $this->contentHtml = $contentHtml; return $this; }
    public function getConclusionHtml(): string { return $this->conclusionHtml; }
    public function setConclusionHtml(string $conclusionHtml): static { $this->conclusionHtml = $conclusionHtml; return $this; }
}
