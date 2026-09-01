<?php

declare(strict_types=1);

use App\Entity\CaseCategory;
use App\Entity\CaseMedia;
use App\Entity\ImagingModality;
use App\Entity\RadiologyCase;
use App\Entity\User;
use App\Enum\CaseDifficulty;
use App\Enum\RadiologyCaseStatus;
use App\Kernel;
use App\Repository\RadiologyCaseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__).'/vendor/autoload.php';
(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

function integrationEnsure(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

$kernel = new Kernel('dev', false);
$kernel->boot();
/** @var EntityManagerInterface $entityManager */
$entityManager = $kernel->getContainer()->get('doctrine')->getManager();
/** @var RadiologyCaseRepository $repository */
$repository = $entityManager->getRepository(RadiologyCase::class);
$connection = $entityManager->getConnection();
$connection->beginTransaction();

try {
    $suffix = bin2hex(random_bytes(6));
    $author = (new User())
        ->setFirstName('Test')
        ->setLastName('Radiologue')
        ->setEmail("case-$suffix@example.test")
        ->setPassword('not-used');
    $modality = $entityManager->getRepository(ImagingModality::class)->findOneBy([]);
    $category = $entityManager->getRepository(CaseCategory::class)->findOneBy([]);
    integrationEnsure($modality instanceof ImagingModality && $category instanceof CaseCategory, 'Les référentiels de migration doivent exister.');

    $draft = buildCase("Brouillon $suffix", "brouillon-$suffix", $author, $modality, $category);
    $publishedOld = buildCase("Publié ancien $suffix", "publie-ancien-$suffix", $author, $modality, $category)
        ->setPublishedAt(new DateTimeImmutable('2100-01-01 10:00:00'))
        ->setStatus(RadiologyCaseStatus::PUBLISHED);
    $publishedRecent = buildCase("Publié récent $suffix", "publie-recent-$suffix", $author, $modality, $category)
        ->setPublishedAt(new DateTimeImmutable('2100-01-02 10:00:00'))
        ->setStatus(RadiologyCaseStatus::PUBLISHED);

    $entityManager->persist($author);
    $entityManager->persist($draft);
    $entityManager->persist($publishedOld);
    $entityManager->persist($publishedRecent);
    $entityManager->flush();

    integrationEnsure($repository->findPublishedBySlug($draft->getSlug()) === null, 'Un brouillon ne doit pas être exposé publiquement.');
    integrationEnsure($repository->findPublishedBySlug($publishedRecent->getSlug()) === $publishedRecent, 'Un cas publié doit être exposé.');
    $recentIds = array_map(static fn (RadiologyCase $case): ?int => $case->getId(), $repository->findRecentPublished(3));
    integrationEnsure(in_array($publishedRecent->getId(), $recentIds, true) && in_array($publishedOld->getId(), $recentIds, true), 'Les cas publiés doivent figurer dans les cas récents.');
    integrationEnsure(array_search($publishedRecent->getId(), $recentIds, true) < array_search($publishedOld->getId(), $recentIds, true), 'Les cas récents doivent être triés par date décroissante.');
    integrationEnsure($repository->createUniqueSlug($publishedRecent->getSlug()) === $publishedRecent->getSlug().'-2', 'Le slug doit être rendu unique.');

    $publishedResponse = $kernel->handle(Request::create('/api/cases/'.$publishedRecent->getSlug(), 'GET'));
    $draftResponse = $kernel->handle(Request::create('/api/cases/'.$draft->getSlug(), 'GET'));
    $adminResponse = $kernel->handle(Request::create('/api/admin/radiology-cases', 'GET'));
    integrationEnsure($publishedResponse->getStatusCode() === 200, 'La route publique doit retourner le cas publié.');
    integrationEnsure($draftResponse->getStatusCode() === 404, 'La route publique doit masquer le brouillon.');
    integrationEnsure($adminResponse->getStatusCode() === 401, 'Le CRUD administrateur doit exiger une authentification.');

    fwrite(STDOUT, "Tests d’intégration PostgreSQL/API : OK\n");
} finally {
    if ($connection->isTransactionActive()) { $connection->rollBack(); }
    $kernel->shutdown();
}

function buildCase(string $title, string $slug, User $author, ImagingModality $modality, CaseCategory $category): RadiologyCase
{
    $case = (new RadiologyCase())
        ->setTitle($title)
        ->setSlug($slug)
        ->setAuthor($author)
        ->setModality($modality)
        ->setCategory($category)
        ->setDifficulty(CaseDifficulty::BEGINNER)
        ->setClinicalContext('Contexte clinique de test.')
        ->setExpertDescription('Description experte de test.')
        ->setDiagnosis('Diagnostic de test.');
    $case->addMedia((new CaseMedia())->setPath('/test/image.jpg')->setIsPrimary(true));

    return $case;
}
