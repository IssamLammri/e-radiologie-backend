<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\{Specialty, MedicalClassification, EvaluationRule, Course, CourseSection, CourseBlock, DemsExercise, ReportExam, ReportTemplate, ImagingModality};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

final class EditorialContent
{
    public const RESOURCES = [
        'specialties' => Specialty::class,
        'classifications' => MedicalClassification::class,
        'courses' => Course::class,
        'dems-exercises' => DemsExercise::class,
        'report-exams' => ReportExam::class,
        'report-templates' => ReportTemplate::class,
    ];
    public const ANSWERS = ['identification', 'analyticStudy', 'syntheticStudy', 'differentialDiagnosis', 'management'];
    public const LAYOUTS = ['IMAGE_LEFT', 'IMAGE_RIGHT', 'IMAGE_TOP', 'IMAGE_BOTTOM'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Autowire(service: 'html_sanitizer.sanitizer.app.editorial')]
        private readonly HtmlSanitizerInterface $sanitizer,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {}

    public function cleanHtml(string $html): string
    {
        return $this->sanitizer->sanitize($html);
    }

    /** Validate the entire document before any entity is changed. */
    public function validate(string $resource, array $data, bool $creating): array
    {
        $errors = [];
        $fields = match ($resource) {
            'specialties' => ['name' => 255],
            'classifications' => ['name' => 255, 'type' => 120, 'description' => 50000, 'indications' => 50000],
            'courses' => ['title' => 255, 'imagePath' => 2048, 'authorName' => 255, 'institution' => 255],
            'dems-exercises' => ['name' => 255, 'maskedName' => 255, 'imagePath' => 2048, 'clinicalPresentation' => 50000] + array_fill_keys(self::ANSWERS, 50000),
            'report-exams' => ['name' => 255],
            'report-templates' => ['label' => 255, 'category' => 16, 'contentHtml' => 200000],
            default => [],
        };
        foreach ($fields as $field => $max) {
            if ($creating || array_key_exists($field, $data)) {
                $this->text($data[$field] ?? null, $field, $max, $errors);
            }
        }
        foreach (['conclusionHtml' => 200000] as $field => $max) {
            if (array_key_exists($field, $data) && (!is_string($data[$field]) || mb_strlen($data[$field]) > $max)) {
                $errors[$field][] = 'Texte invalide ou trop long.';
            }
        }
        $relation = match ($resource) {
            'classifications', 'courses' => ['specialtyId', Specialty::class],
            'report-exams' => ['modalityId', ImagingModality::class],
            'report-templates' => ['examId', ReportExam::class],
            default => null,
        };
        if ($relation && ($creating || array_key_exists($relation[0], $data))) {
            $id = $data[$relation[0]] ?? null;
            if (!is_int($id) || $id < 1 || !$this->em->find($relation[1], $id)) {
                $errors[$relation[0]][] = 'Référence inexistante ou invalide.';
            }
        }
        if (isset($data['category']) && !in_array($data['category'], ['NORMAL', 'PATHOLOGICAL'], true)) {
            $errors['category'][] = 'Choisir NORMAL ou PATHOLOGICAL.';
        }
        if (array_key_exists('position', $data) && (!is_int($data['position']) || $data['position'] < 0)) {
            $errors['position'][] = 'L’ordre doit être un entier positif ou nul.';
        }
        if (array_key_exists('imagePath', $data) && !$this->validMediaPath($data['imagePath'])) {
            $errors['imagePath'][] = 'Utiliser une image importée ou une URL HTTP(S).';
        }
        if (array_key_exists('rules', $data)) {
            if (!is_array($data['rules']) || !array_is_list($data['rules']) || count($data['rules']) > 200) {
                $errors['rules'][] = 'Liste de règles invalide (200 maximum).';
            } else {
                foreach ($data['rules'] as $i => $rule) {
                    foreach (['grade', 'semiology', 'clinicalDescription'] as $field) {
                        $this->text(is_array($rule) ? ($rule[$field] ?? null) : null, "rules.$i.$field", $field === 'grade' ? 255 : 50000, $errors);
                    }
                }
            }
        }
        if (array_key_exists('sections', $data)) {
            $count = 0;
            $this->validateSections($data['sections'], 'sections', $errors, 0, $count);
        }
        if (isset($data['contentHtml']) && is_string($data['contentHtml']) && trim(strip_tags($this->cleanHtml($data['contentHtml']))) === '') {
            $errors['contentHtml'][] = 'Le contenu du modèle doit contenir du texte.';
        }
        return $errors;
    }

    private function text(mixed $value, string $field, int $max, array &$errors): void
    {
        if (!is_string($value) || trim($value) === '' || mb_strlen($value) > $max) {
            $errors[$field][] = "Ce texte est obligatoire (maximum $max caractères).";
        }
    }

    public function validMediaPath(mixed $value): bool
    {
        if (!is_string($value) || strlen($value) > 2048) { return false; }
        if (preg_match('~^/uploads/editorial/[a-f0-9]{32}\.(png|jpg|webp)$~D', $value)) { return is_file($this->projectDir.'/public'.$value); }
        return filter_var($value, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true)
            && parse_url($value, PHP_URL_USER) === null;
    }

    private function validateSections(mixed $sections, string $path, array &$errors, int $depth, int &$count): void
    {
        if (!is_array($sections) || !array_is_list($sections) || $depth > 10 || ($count += count($sections)) > 500) {
            $errors[$path][] = 'Arborescence invalide (10 niveaux et 500 sections maximum).';
            return;
        }
        foreach ($sections as $i => $section) {
            if (!is_array($section)) { $errors["$path.$i"][] = 'Section invalide.'; continue; }
            $this->text($section['title'] ?? null, "$path.$i.title", 255, $errors);
            $blocks = $section['blocks'] ?? [];
            if (!is_array($blocks) || !array_is_list($blocks) || count($blocks) > 100) {
                $errors["$path.$i.blocks"][] = 'Liste de blocs invalide (100 maximum).';
            } else {
                foreach ($blocks as $j => $block) {
                    $key = "$path.$i.blocks.$j";
                    if (!is_array($block)) { $errors[$key][] = 'Bloc invalide.'; continue; }
                    $html = $block['contentHtml'] ?? '';
                    $image = $block['imagePath'] ?? null;
                    if ($image === '') { $image = null; }
                    if (!is_string($html) || mb_strlen($html) > 200000) { $errors[$key][] = 'Texte du bloc invalide.'; }
                    if ($image !== null && !$this->validMediaPath($image)) { $errors[$key][] = 'Image invalide.'; }
                    if (!$image && (!is_string($html) || trim(strip_tags($this->cleanHtml($html))) === '')) { $errors[$key][] = 'Un bloc doit contenir un texte ou une image.'; }
                    if (!in_array($block['layout'] ?? 'IMAGE_LEFT', self::LAYOUTS, true)) { $errors[$key][] = 'Disposition invalide.'; }
                }
            }
            $this->validateSections($section['children'] ?? [], "$path.$i.children", $errors, $depth + 1, $count);
        }
    }

    public function apply(object $entity, array $data): void
    {
        $plain = match (true) {
            $entity instanceof Specialty => ['name', 'position'],
            $entity instanceof MedicalClassification => ['name', 'type', 'description', 'indications'],
            $entity instanceof Course => ['title', 'imagePath', 'authorName', 'institution'],
            $entity instanceof DemsExercise => ['name', 'maskedName', 'imagePath', 'clinicalPresentation', ...self::ANSWERS],
            $entity instanceof ReportExam => ['name'],
            $entity instanceof ReportTemplate => ['label', 'category'],
            default => [],
        };
        foreach ($plain as $field) {
            if (array_key_exists($field, $data)) { $entity->{'set'.ucfirst($field)}(is_string($data[$field]) ? trim($data[$field]) : $data[$field]); }
        }
        if ($entity instanceof MedicalClassification || $entity instanceof Course) {
            if (isset($data['specialtyId'])) { $entity->setSpecialty($this->em->find(Specialty::class, $data['specialtyId'])); }
        }
        if ($entity instanceof ReportExam && isset($data['modalityId'])) { $entity->setModality($this->em->find(ImagingModality::class, $data['modalityId'])); }
        if ($entity instanceof ReportTemplate) {
            if (isset($data['examId'])) { $entity->setExam($this->em->find(ReportExam::class, $data['examId'])); }
            foreach (['contentHtml', 'conclusionHtml'] as $field) {
                if (isset($data[$field])) { $entity->{'set'.ucfirst($field)}($this->cleanHtml($data[$field])); }
            }
        }
        if ($entity instanceof MedicalClassification && array_key_exists('rules', $data)) {
            foreach ($entity->getRules()->toArray() as $rule) { $entity->removeRule($rule); }
            foreach ($data['rules'] as $i => $item) {
                $entity->addRule((new EvaluationRule())->setGrade(trim($item['grade']))->setSemiology(trim($item['semiology']))->setClinicalDescription(trim($item['clinicalDescription']))->setPosition($i));
            }
        }
        if ($entity instanceof Course && array_key_exists('sections', $data)) {
            foreach ($entity->getSections()->toArray() as $section) { $entity->removeSection($section); }
            $this->addSections($entity, $data['sections']);
        }
    }

    private function addSections(Course $course, array $sections, ?CourseSection $parent = null): void
    {
        foreach ($sections as $i => $data) {
            $section = (new CourseSection())->setTitle(trim($data['title']))->setPosition($i)->setParent($parent);
            $course->addSection($section);
            foreach ($data['blocks'] ?? [] as $j => $item) {
                $section->addBlock((new CourseBlock())->setPosition($j)->setContentHtml($this->cleanHtml($item['contentHtml'] ?? ''))->setImagePath(($item['imagePath'] ?? null) ?: null)->setLayout($item['layout'] ?? 'IMAGE_LEFT'));
            }
            $this->addSections($course, $data['children'] ?? [], $section);
        }
    }

    public function normalize(object $entity, bool $detail = true, bool $admin = false): array
    {
        $result = ['id' => $entity->getId()];
        $fields = match (true) {
            $entity instanceof Specialty => ['name', 'position'],
            $entity instanceof MedicalClassification => ['name', 'type', 'description', 'indications', 'calculatorKey'],
            $entity instanceof Course => ['title', 'imagePath', 'authorName', 'institution'],
            $entity instanceof DemsExercise => ['name', 'maskedName', 'imagePath', 'clinicalPresentation'],
            $entity instanceof ReportExam => ['name'],
            $entity instanceof ReportTemplate => ['label', 'category', 'contentHtml', 'conclusionHtml'],
            default => [],
        };
        foreach ($fields as $field) { $result[$field] = $entity->{'get'.ucfirst($field)}(); }
        if ($entity instanceof Specialty) {
            $result['classificationCount'] = $this->em->getRepository(MedicalClassification::class)->count(['specialty' => $entity]);
            $result['courseCount'] = $this->em->getRepository(Course::class)->count(['specialty' => $entity]);
        }
        if ($entity instanceof MedicalClassification || $entity instanceof Course) {
            $result['specialty'] = ['id' => $entity->getSpecialty()->getId(), 'name' => $entity->getSpecialty()->getName()];
            $result['specialtyId'] = $entity->getSpecialty()->getId();
        }
        if ($entity instanceof MedicalClassification) {
            $result['rules'] = array_values(array_map(static fn (EvaluationRule $r) => ['id' => $r->getId(), 'grade' => $r->getGrade(), 'semiology' => $r->getSemiology(), 'clinicalDescription' => $r->getClinicalDescription(), 'position' => $r->getPosition()], $entity->getRules()->toArray()));
        }
        if ($entity instanceof Course && $detail) { $result['sections'] = $this->sections($entity); }
        if ($entity instanceof DemsExercise && $admin) { $result['answers'] = $this->answers($entity); }
        if ($entity instanceof ReportExam) {
            $result['modalityId'] = $entity->getModality()->getId();
            $result['modality'] = ['id' => $entity->getModality()->getId(), 'name' => $entity->getModality()->getName()];
            $result['templateCount'] = $this->em->getRepository(ReportTemplate::class)->count(['exam' => $entity]);
        }
        if ($entity instanceof ReportTemplate) {
            $result['examId'] = $entity->getExam()->getId();
            $result['exam'] = ['id' => $entity->getExam()->getId(), 'name' => $entity->getExam()->getName()];
        }
        return $result;
    }

    public function answers(DemsExercise $entity): array
    {
        $answers = [];
        foreach (self::ANSWERS as $field) { $answers[$field] = $entity->{'get'.ucfirst($field)}(); }
        return $answers;
    }

    private function sections(Course $course, ?CourseSection $parent = null): array
    {
        $sections = array_filter($course->getSections()->toArray(), static fn (CourseSection $s) => $s->getParent() === $parent);
        usort($sections, static fn (CourseSection $a, CourseSection $b) => $a->getPosition() <=> $b->getPosition());
        return array_values(array_map(fn (CourseSection $s) => [
            'id' => $s->getId(), 'title' => $s->getTitle(), 'position' => $s->getPosition(),
            'blocks' => array_values(array_map(static fn (CourseBlock $b) => ['id' => $b->getId(), 'contentHtml' => $b->getContentHtml(), 'imagePath' => $b->getImagePath(), 'layout' => $b->getLayout(), 'position' => $b->getPosition()], $s->getBlocks()->toArray())),
            'children' => $this->sections($course, $s),
        ], $sections));
    }
}
