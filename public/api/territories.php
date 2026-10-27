<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/src/Database.php';
require_once dirname(__DIR__, 2) . '/src/Response.php';

use Viagem\Response;

try {
    $pdo = \Viagem\Database::connect();
} catch (\RuntimeException $e) {
    Response::error(500, $e->getMessage());
}

$rows = $pdo->query('
    SELECT ku,
           MIN(min_lng) AS min_lng, MIN(min_lat) AS min_lat,
           MAX(max_lng) AS max_lng, MAX(max_lat) AS max_lat,
           COUNT(*)     AS parcels
    FROM parcels
    GROUP BY ku
')->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($rows, JSON_UNESCAPED_UNICODE);