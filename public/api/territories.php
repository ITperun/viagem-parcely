<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$pdo = new PDO('sqlite:' . dirname(__DIR__, 2) . '/data/parcels.sqlite', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$rows = $pdo->query('
    SELECT ku,
           MIN(min_lng) AS min_lng, MIN(min_lat) AS min_lat,
           MAX(max_lng) AS max_lng, MAX(max_lat) AS max_lat,
           COUNT(*)     AS parcels
    FROM parcels
    GROUP BY ku
')->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($rows, JSON_UNESCAPED_UNICODE);