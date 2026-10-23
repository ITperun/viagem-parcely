<?php
declare(strict_types=1);

header('Content-Type: application/geo+json; charset=utf-8');

function fail(int $code, string $message): never
{
    http_response_code($code);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Читаем и проверяем границы карты
$bbox = [];
foreach (['minLng', 'minLat', 'maxLng', 'maxLat'] as $key) {
    if (!isset($_GET[$key]) || !is_numeric($_GET[$key])) {
        fail(400, "Parametr $key chybí nebo není číslo");
    }
    $bbox[$key] = (float) $_GET[$key];
}

if ($bbox['minLng'] > $bbox['maxLng'] || $bbox['minLat'] > $bbox['maxLat']) {
    fail(400, 'Neplatné hranice: min je větší než max');
}

// 2. Ограничение объема ответа
const LIMIT = 3000;

$dbPath = dirname(__DIR__, 2) . '/data/parcels.sqlite';
if (!is_file($dbPath)) {
    fail(500, 'Databáze není připravena, spusťte scripts/import.php');
}

$pdo = new PDO('sqlite:' . $dbPath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// 3. Выбираем парцелы, bbox которых пересекает экран
// Пересечение двух прямоугольников: min одного <= max другого и наоборот
$stmt = $pdo->prepare('
    SELECT id, label, area, national_ref, geometry
    FROM parcels
    WHERE max_lng >= :minLng AND min_lng <= :maxLng
      AND max_lat >= :minLat AND min_lat <= :maxLat
    LIMIT :limit
');
$stmt->bindValue(':minLng', $bbox['minLng']);
$stmt->bindValue(':maxLng', $bbox['maxLng']);
$stmt->bindValue(':minLat', $bbox['minLat']);
$stmt->bindValue(':maxLat', $bbox['maxLat']);
$stmt->bindValue(':limit', LIMIT + 1, PDO::PARAM_INT);
$stmt->execute();

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$truncated = count($rows) > LIMIT;

// 4. Собираем GeoJSON. Геометрия уже хранится как JSON-строка.
$features = [];
foreach (array_slice($rows, 0, LIMIT) as $row) {
    $properties = json_encode([
        'id'                         => $row['id'],
        'label'                      => $row['label'],
        'areaValue'                  => $row['area'],
        'nationalCadastralReference' => $row['national_ref'],
    ], JSON_UNESCAPED_UNICODE);

    $features[] = '{"type":"Feature","properties":' . $properties
        . ',"geometry":' . $row['geometry'] . '}';
}

echo '{"type":"FeatureCollection","truncated":' . ($truncated ? 'true' : 'false')
    . ',"features":[' . implode(',', $features) . ']}';