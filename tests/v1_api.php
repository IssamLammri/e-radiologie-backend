<?php

declare(strict_types=1);

// Runs against the local HTTP API, with synthetic text only. All created records are removed.
$base = rtrim(getenv('V1_API_URL') ?: 'http://127.0.0.1:8000', '/');
function check(bool $condition, string $message): void { if (!$condition) { throw new RuntimeException($message); } }
function callApi(string $path, string $method = 'GET', ?array $body = null, ?string $token = null, int $expected = 200): array
{
    global $base;
    $headers = ['Accept: application/json'];
    if ($body !== null) { $headers[] = 'Content-Type: application/json'; }
    if ($token) { $headers[] = 'Authorization: Bearer '.$token; }
    $context = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $headers), 'content' => $body === null ? '' : json_encode($body, JSON_THROW_ON_ERROR), 'ignore_errors' => true, 'timeout' => 20]]);
    $raw = file_get_contents($base.$path, false, $context);
    preg_match('~HTTP/\S+ (\d+)~', $http_response_header[0] ?? '', $matches);
    check((int) ($matches[1] ?? 0) === $expected, "$method $path: expected $expected, got ".($matches[1] ?? 'no response'));
    $data = $raw === '' ? [] : json_decode($raw ?: '{}', true);
    check(is_array($data), "$method $path: expected JSON");
    return $data;
}
function auth(string $email): string { return callApi('/api/login_check', 'POST', ['email' => $email, 'password' => 'LocalTest123!'])['token']; }
$admin = auth('admin@e-radiologie.test');
$doctor = auth('client@e-radiologie.test');
$suffix = bin2hex(random_bytes(5));
$cleanup = [];
$privateId = null;
$modalityId = null;
function createContent(string $resource, array $data): array
{
    global $admin, $cleanup;
    $created = callApi('/api/admin/content/'.$resource, 'POST', $data, $admin, 201);
    $cleanup[] = [$resource, $created['id']];
    return $created;
}
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aF1cAAAAASUVORK5CYII=');
$mediaPath = null;

try {
    foreach (['specialties', 'classifications', 'courses', 'dems-exercises', 'report-exams', 'report-templates'] as $resource) {
        callApi('/api/admin/content/'.$resource, 'POST', [], null, 401);
        callApi('/api/admin/content/'.$resource, 'POST', [], $doctor, 403);
        callApi('/api/admin/content/'.$resource, 'GET', null, $doctor, 403);
    }
    callApi('/api/admin/content/specialties', 'POST', ['name' => ''], $admin, 422);
    $s = createContent('specialties', ['name' => "Test V1 $suffix", 'position' => 0]);
    $c = createContent('classifications', ['specialtyId' => $s['id'], 'name' => "Classification $suffix", 'type' => 'TEST', 'description' => 'Description de test.', 'indications' => 'Indication éditoriale de test.', 'rules' => [
        ['grade' => 'B', 'semiology' => 'Texte B', 'clinicalDescription' => 'Description B'],
        ['grade' => 'A', 'semiology' => 'Texte A', 'clinicalDescription' => 'Description A'],
    ]]);
    $list = callApi('/api/content/classifications?specialty='.$s['id'].'&search='.urlencode($suffix));
    check(count($list['items']) === 1 && $list['items'][0]['rules'][0]['grade'] === 'B', 'Classification search/filter/order');
    $c = callApi('/api/admin/content/classifications/'.$c['id'], 'PATCH', ['rules' => array_reverse($c['rules'])], $admin);
    check($c['rules'][0]['grade'] === 'A', 'Rules can be reordered');
    callApi('/api/admin/content/specialties/'.$s['id'], 'DELETE', null, $admin, 409);
    echo "1. Spécialités, classifications, recherche, ordre et dépendances : OK\n";

    $boundary = 'er'.bin2hex(random_bytes(8));
    $multipart = "--$boundary\r\nContent-Disposition: form-data; name=\"file\"; filename=\"test.png\"\r\nContent-Type: image/png\r\n\r\n".$png."\r\n--$boundary--\r\n";
    $context = stream_context_create(['http' => ['method' => 'POST', 'header' => "Authorization: Bearer $admin\r\nContent-Type: multipart/form-data; boundary=$boundary", 'content' => $multipart, 'ignore_errors' => true]]);
    $upload = json_decode(file_get_contents($base.'/api/admin/media', false, $context), true);
    check(isset($upload['path']), 'Validated media upload');
    $mediaPath = $upload['path'];
    $image = $mediaPath;
    $blocks = [ ['contentHtml' => '<p>Texte seul <strong>conservé</strong><script>alert(1)</script><a href="javascript:alert(1)">lien</a></p>', 'imagePath' => null, 'layout' => 'IMAGE_LEFT'], ['contentHtml' => '', 'imagePath' => $image, 'layout' => 'IMAGE_LEFT'] ];
    foreach (['IMAGE_LEFT', 'IMAGE_RIGHT', 'IMAGE_TOP', 'IMAGE_BOTTOM'] as $layout) $blocks[] = ['contentHtml' => '<p>Texte et image.</p>', 'imagePath' => $image, 'layout' => $layout];
    $course = createContent('courses', ['specialtyId' => $s['id'], 'title' => "Cours $suffix", 'imagePath' => $image, 'authorName' => 'Rédacteur test', 'institution' => 'Établissement test', 'sections' => [ ['title' => 'Introduction', 'blocks' => $blocks, 'children' => [['title' => 'Sous-titre', 'blocks' => [], 'children' => []]]], ['title' => 'Deuxième section', 'blocks' => [], 'children' => []] ]]);
    $public = callApi('/api/content/courses/'.$course['id']);
    check(count($public['sections'][0]['blocks']) === 6 && $public['sections'][0]['children'][0]['title'] === 'Sous-titre', 'Course tree and block layouts');
    check(!str_contains($public['sections'][0]['blocks'][0]['contentHtml'], '<script') && !str_contains($public['sections'][0]['blocks'][0]['contentHtml'], 'javascript:'), 'HTML sanitizer');
    $updated = callApi('/api/admin/content/courses/'.$course['id'], 'PATCH', ['sections' => array_reverse($public['sections'])], $admin);
    check($updated['sections'][0]['title'] === 'Deuxième section', 'Course sections can be reordered');
    echo "2. Cours imbriqués, six blocs, quatre dispositions, images et HTML assaini : OK\n";

    $refs = ['identification' => 'Référence 1', 'analyticStudy' => 'Référence 2', 'syntheticStudy' => 'Référence 3', 'differentialDiagnosis' => 'Référence 4', 'management' => 'Référence 5'];
    $exercise = createContent('dems-exercises', ['name' => "Planchage $suffix", 'maskedName' => "Titre alternatif $suffix", 'imagePath' => $image, 'clinicalPresentation' => 'Énoncé de test.'] + $refs);
    $public = callApi('/api/content/dems-exercises/'.$exercise['id']);
    check(!isset($public['answers']) && !isset($public['identification']), 'References must not be sent before reveal');
    check(callApi('/api/content/dems-exercises/'.$exercise['id'].'/results', 'POST')['answers'] === $refs, 'Explicit reveal returns references');
    echo "3. DEMS : énoncé séparé et références révélées uniquement par action explicite : OK\n";

    $modality = callApi('/api/admin/imaging-modalities', 'POST', ['name' => 'Modalité test '.$suffix, 'code' => 'TEST_'.$suffix, 'active' => true, 'position' => 0], $admin, 201);
    $modalityId = $modality['id'];
    $exam = createContent('report-exams', ['name' => "Examen $suffix", 'modalityId' => $modality['id']]);
    $template = createContent('report-templates', ['examId' => $exam['id'], 'category' => 'NORMAL', 'label' => 'Modèle test', 'contentHtml' => '<h2>Texte de test</h2><p>Contenu éditorial.</p><img src="x" onerror="alert(1)">', 'conclusionHtml' => '<p>Conclusion de test.</p>']);
    createContent('report-templates', ['examId' => $exam['id'], 'category' => 'PATHOLOGICAL', 'label' => 'Autre modèle test', 'contentHtml' => '<p>Autre texte de test.</p>', 'conclusionHtml' => '']);
    check(count(callApi('/api/content/report-templates?exam='.$exam['id'])['items']) === 2, 'Both categories selectable');
    check(!str_contains($template['contentHtml'], 'onerror'), 'Report templates HTML sanitized');
    $before = callApi('/api/me/reports', token: $doctor)['items'];
    callApi('/api/content/report-exams'); callApi('/api/content/report-templates?exam='.$exam['id']);
    check(callApi('/api/me/reports', token: $doctor)['items'] === $before, 'Reading templates cannot create a private record');
    echo "4–5. Examens et modèles normal/pathologique ; aucune sauvegarde pendant la lecture : OK\n";

    $document = ['title' => "Compte rendu test $suffix", 'contentHtml' => '<p>Texte de test.</p>', 'conclusionHtml' => '<p>Conclusion de test.</p>', 'personalization' => ['patientName' => 'Patient synthétique de test', 'clinicName' => 'Centre test']];
    callApi('/api/me/reports', 'POST', $document, $doctor, 422);
    callApi('/api/me/reports', 'POST', $document + ['saveExplicitly' => true], null, 401);
    $private = callApi('/api/me/reports', 'POST', $document + ['saveExplicitly' => true, 'ownerId' => 999999], $doctor, 201);
    $privateId = $private['id'];
    check($private['personalization']['patientName'] === 'Patient synthétique de test', 'Explicit owner save');
    callApi('/api/me/reports/'.$privateId, token: $admin, expected: 404);
    callApi('/api/me/reports/'.$privateId, 'PATCH', $document + ['saveExplicitly' => true], $admin, 404);
    callApi('/api/me/reports/'.$privateId, 'DELETE', null, $admin, 404);
    callApi('/api/admin/content/saved-reports', token: $admin, expected: 404);
    $summary = callApi('/api/me/reports', token: $doctor)['items'];
    check(!isset($summary[0]['personalization']), 'Private report index omits patient fields');
    callApi('/api/me/reports/'.$privateId, 'PATCH', array_replace($document, ['saveExplicitly' => true, 'title' => 'Titre modifié']), $doctor);
    callApi('/api/me/reports/'.$privateId, 'DELETE', null, $doctor, 204); $privateId = null;
    echo "6. Sauvegarde volontaire, modification et suppression privées ; accès tiers/admin refusé : OK\n";

    callApi('/api/admin/content/courses', 'POST', ['specialtyId' => $s['id'], 'title' => 'Invalide', 'imagePath' => 'javascript:alert(1)', 'authorName' => 'Test', 'institution' => 'Test'], $admin, 422);
    callApi('/api/admin/content/courses/'.$course['id'], 'PATCH', ['sections' => [['title' => 'Test', 'blocks' => [['contentHtml' => '', 'imagePath' => null]], 'children' => []]]], $admin, 422);
    echo "7. Autorisations, validation des médias, blocs vides et HTML : OK\n";
} finally {
    if ($privateId) { callApi('/api/me/reports/'.$privateId, 'DELETE', null, $doctor, 204); }
    foreach (array_reverse($cleanup) as [$resource, $id]) { callApi('/api/admin/content/'.$resource.'/'.$id, 'DELETE', null, $admin, 204); }
    if ($modalityId) { callApi('/api/admin/imaging-modalities/'.$modalityId, 'DELETE', null, $admin, 204); }
    if ($mediaPath && preg_match('~^/uploads/editorial/[a-f0-9]{32}\.png$~D', $mediaPath)) { @unlink(dirname(__DIR__).'/public'.$mediaPath); }
}
echo "Tests API V1 : OK — données temporaires supprimées.\n";
