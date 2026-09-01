<?php

declare(strict_types=1);

use App\Entity\CaseCategory;
use App\Entity\CaseMedia;
use App\Entity\CaseReference;
use App\Entity\ImagingModality;
use App\Entity\RadiologyCase;
use App\Entity\User;
use App\Enum\CaseDifficulty;
use App\Enum\PatientGender;
use App\Enum\RadiologyCaseStatus;
use App\Controller\AdminRadiologyCaseController;
use App\Controller\PublicRadiologyCaseController;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__).'/vendor/autoload.php';

function ensure(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$author = (new User())->setFirstName('Alice')->setLastName('Martin')->setEmail('alice@example.test');
$modality = (new ImagingModality())->setName('Radiographie')->setSlug('radiographie')->setCode('RADIOGRAPHY');
$category = (new CaseCategory())->setName('Imagerie thoracique')->setSlug('imagerie-thoracique');

$case = (new RadiologyCase())
    ->setTitle('Pneumonie lobaire franche aiguë')
    ->setSlug('pneumonie-lobaire-franche-aigue')
    ->setModality($modality)
    ->setCategory($category)
    ->setDifficulty(CaseDifficulty::BEGINNER)
    ->setPatientGender(PatientGender::MALE)
    ->setPatientAge(45)
    ->setClinicalContext('Fièvre à 39°C et toux grasse.')
    ->setAuthor($author)
    ->setExpertDescription('Opacité alvéolaire systématisée.')
    ->setDiagnosis('Pneumonie lobaire inférieure droite.');

$face = (new CaseMedia())->setPath('/media/face.jpg')->setTitle('Face')->setIsPrimary(true);
$profile = (new CaseMedia())->setPath('/media/profile.jpg')->setTitle('Profil');
$case->addMedia($face)->addMedia($profile);
ensure($case->getMedia()->count() === 2, 'Un cas doit accepter plusieurs médias.');
ensure($case->getPrimaryMedia() === $face, 'Le premier média principal doit être retourné.');

$profile->setIsPrimary(true);
ensure(!$face->isPrimary() && $profile->isPrimary(), 'Un seul média doit rester principal.');

$reference = (new CaseReference())->setTitle('Collège des Enseignants de Radiologie')->setSource('CERF');
$case->addReference($reference);
ensure($case->getReferences()->count() === 1, 'Une référence doit pouvoir être associée au cas.');
ensure($case->getAuthor() === $author, 'L’auteur du cas doit être le User existant.');
ensure($case->getModality() === $modality && $case->getCategory() === $category, 'Les référentiels doivent être associés.');

$case->setStatus(RadiologyCaseStatus::PUBLISHED);
ensure($case->getPublishedAt() instanceof DateTimeImmutable, 'La publication doit renseigner publishedAt.');
ensure(CaseDifficulty::BEGINNER->label() === 'Débutant', 'Le libellé français du niveau doit être exposé.');

$requestWithEmptyFilters = Request::create('/api/cases?modality=&category=');
foreach ([new PublicRadiologyCaseController(), new AdminRadiologyCaseController()] as $controller) {
    $positiveId = new ReflectionMethod($controller, 'positiveId');
    ensure($positiveId->invoke($controller, $requestWithEmptyFilters, 'modality') === null, 'Un filtre numérique vide doit être considéré comme absent.');
}

$case->initializeTimestamps();
ensure($case->getCreatedAt() instanceof DateTimeImmutable && $case->getUpdatedAt() instanceof DateTimeImmutable, 'Les timestamps doivent être initialisés.');

fwrite(STDOUT, "Tests métier des cas cliniques : OK\n");
