<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/Response.php';
require_once dirname(__DIR__, 2) . '/src/Database.php';

use Viagem\Response;
use Viagem\Database;

header('Content-Type: application/geo+json; charset=utf-8');

const LIMIT = 2000;

// 1. Читаем и проверяем границы карты
$bbox = [];
foreach (['minLng', 'minLat', 'maxLng', 'maxLat'] as $key) {
    if (!isset($_GET[$key]) || !is_numeric($_GET[$key])) {
        Response::error(400, "Parametr $key chybí nebo není číslo");
    }
    $bbox[$key] = (float) $_GET[$key];
}

if ($bbox['minLng'] > $bbox['maxLng'] || $bbox['minLat'] > $bbox['maxLat']) {
    Response::error(400, 'Neplatné hranice: min je větší než max');
}

// 2. Подключаемся к базе
try {
    $pdo = Database::connect();
} catch (\RuntimeException $e) {
    Response::error(500, $e->getMessage());
}

// 3. Выбираем парцелы, bbox которых пересекает экран.
// Пересечение двух прямоугольников: min одного <= max другого и наоборот.
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

// Если парцел слишком много, не отдаём неполный набор, а только сообщаем об этом
if ($truncated) {
    echo '{"type":"FeatureCollection","truncated":true,"features":[]}';
    exit;
}

// 4. Собираем GeoJSON. Геометрия уже хранится как JSON-строка.
$features = [];
foreach ($rows as $row) {
    $properties = json_encode([
        'id'                         => $row['id'],
        'label'                      => $row['label'],
        'areaValue'                  => $row['area'],
        'nationalCadastralReference' => $row['national_ref'],
    ], JSON_UNESCAPED_UNICODE);

    $features[] = '{"type":"Feature","properties":' . $properties
        . ',"geometry":' . $row['geometry'] . '}';
}

echo '{"type":"FeatureCollection","truncated":false,"features":[' . implode(',', $features) . ']}';