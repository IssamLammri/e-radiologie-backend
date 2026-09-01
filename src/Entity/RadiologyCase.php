<?php

namespace App\Entity;

use App\Entity\Traits\TimestampableTrait;
use App\Enum\CaseDifficulty;
use App\Enum\PatientGender;
use App\Enum\RadiologyCaseStatus;
use App\Repository\RadiologyCaseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RadiologyCaseRepository::class)]
#[ORM\Table(name: 'radiology_case')]
#[ORM\Index(name: 'idx_radiology_case_status', fields: ['status'])]
#[ORM\Index(name: 'idx_radiology_case_published_at', fields: ['publishedAt'])]
#[ORM\HasLifecycleCallbacks]
class RadiologyCase
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[ORM\Column(length: 255, unique: true)]
    private ?string $slug = null;

    #[Assert\NotNull]
    #[ORM\ManyToOne(inversedBy: 'radiologyCases')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?ImagingModality $modality = null;

    #[Assert\NotNull]
    #[ORM\ManyToOne(inversedBy: 'radiologyCases')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?CaseCategory $category = null;

    #[Assert\NotNull]
    #[ORM\Column(length: 24, enumType: CaseDifficulty::class)]
    private CaseDifficulty $difficulty = CaseDifficulty::BEGINNER;

    #[Assert\NotNull]
    #[ORM\Column(length: 24, enumType: PatientGender::class)]
    private PatientGender $patientGender = PatientGender::NOT_SPECIFIED;

    #[Assert\PositiveOrZero]
    #[ORM\Column(nullable: true)]
    private ?int $patientAge = null;

    #[Assert\NotBlank]
    #[ORM\Column(type: Types::TEXT)]
    private ?string $clinicalContext = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $trainingInstruction = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $trainingPlaceholder = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $expertDescription = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $diagnosis = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $globalDiscussion = null;

    #[Assert\NotNull]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?User $author = null;

    #[Assert\NotNull]
    #[ORM\Column(length: 28, enumType: RadiologyCaseStatus::class)]
    private RadiologyCaseStatus $status = RadiologyCaseStatus::DRAFT;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    /** @var Collection<int, CaseMedia> */
    #[ORM\OneToMany(mappedBy: 'radiologyCase', targetEntity: CaseMedia::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $media;

    /** @var Collection<int, CaseReference> */
    #[ORM\OneToMany(mappedBy: 'radiologyCase', targetEntity: CaseReference::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $references;

    public function __construct()
    {
        $this->media = new ArrayCollection();
        $this->references = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): static { $this->title = trim($title); return $this; }
    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(string $slug): static { $this->slug = trim($slug); return $this; }
    public function getModality(): ?ImagingModality { return $this->modality; }
    public function setModality(ImagingModality $modality): static { $this->modality = $modality; return $this; }
    public function getCategory(): ?CaseCategory { return $this->category; }
    public function setCategory(CaseCategory $category): static { $this->category = $category; return $this; }
    public function getDifficulty(): CaseDifficulty { return $this->difficulty; }
    public function setDifficulty(CaseDifficulty $difficulty): static { $this->difficulty = $difficulty; return $this; }
    public function getPatientGender(): PatientGender { return $this->patientGender; }
    public function setPatientGender(PatientGender $patientGender): static { $this->patientGender = $patientGender; return $this; }
    public function getPatientAge(): ?int { return $this->patientAge; }
    public function setPatientAge(?int $patientAge): static { $this->patientAge = $patientAge; return $this; }
    public function getClinicalContext(): ?string { return $this->clinicalContext; }
    public function setClinicalContext(string $clinicalContext): static { $this->clinicalContext = trim($clinicalContext); return $this; }
    public function getTrainingInstruction(): ?string { return $this->trainingInstruction; }
    public function setTrainingInstruction(?string $value): static { $this->trainingInstruction = self::trimNullable($value); return $this; }
    public function getTrainingPlaceholder(): ?string { return $this->trainingPlaceholder; }
    public function setTrainingPlaceholder(?string $value): static { $this->trainingPlaceholder = self::trimNullable($value); return $this; }
    public function getExpertDescription(): ?string { return $this->expertDescription; }
    public function setExpertDescription(?string $value): static { $this->expertDescription = self::trimNullable($value); return $this; }
    public function getDiagnosis(): ?string { return $this->diagnosis; }
    public function setDiagnosis(?string $value): static { $this->diagnosis = self::trimNullable($value); return $this; }
    public function getGlobalDiscussion(): ?string { return $this->globalDiscussion; }
    public function setGlobalDiscussion(?string $value): static { $this->globalDiscussion = self::trimNullable($value); return $this; }
    public function getAuthor(): ?User { return $this->author; }
    public function setAuthor(User $author): static { $this->author = $author; return $this; }
    public function getStatus(): RadiologyCaseStatus { return $this->status; }

    public function setStatus(RadiologyCaseStatus $status): static
    {
        $this->status = $status;
        if ($status === RadiologyCaseStatus::PUBLISHED && $this->publishedAt === null) {
            $this->publishedAt = new \DateTimeImmutable();
        }
        return $this;
    }

    public function getPublishedAt(): ?\DateTimeImmutable { return $this->publishedAt; }
    public function setPublishedAt(?\DateTimeImmutable $publishedAt): static { $this->publishedAt = $publishedAt; return $this; }

    /** @return Collection<int, CaseMedia> */
    public function getMedia(): Collection { return $this->media; }

    public function addMedia(CaseMedia $media): static
    {
        if (!$this->media->contains($media)) {
            if ($media->isPrimary()) {
                foreach ($this->media as $existingMedia) {
                    $existingMedia->setIsPrimary(false);
                }
            }
            $this->media->add($media);
            $media->setRadiologyCase($this);
        }
        return $this;
    }

    public function removeMedia(CaseMedia $media): static
    {
        if ($this->media->removeElement($media) && $media->getRadiologyCase() === $this) {
            $media->setRadiologyCase(null);
        }
        return $this;
    }

    public function getPrimaryMedia(): ?CaseMedia
    {
        foreach ($this->media as $media) {
            if ($media->isPrimary()) {
                return $media;
            }
        }
        return $this->media->first() ?: null;
    }

    /** @return Collection<int, CaseReference> */
    public function getReferences(): Collection { return $this->references; }

    public function addReference(CaseReference $reference): static
    {
        if (!$this->references->contains($reference)) {
            $this->references->add($reference);
            $reference->setRadiologyCase($this);
        }
        return $this;
    }

    public function removeReference(CaseReference $reference): static
    {
        if ($this->references->removeElement($reference) && $reference->getRadiologyCase() === $this) {
            $reference->setRadiologyCase(null);
        }
        return $this;
    }

    private static function trimNullable(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        return $value === '' ? null : $value;
    }
}
