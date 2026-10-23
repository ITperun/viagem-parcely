<?php
declare(strict_types=1);

// Импорт GeoJSON-файлов из data/ в SQLite (data/parcels.sqlite).
// Запуск из корня проекта: php scripts/import.php

$root    = dirname(__DIR__);
$dataDir = $root . '/data';
$dbPath  = $dataDir . '/parcels.sqlite';

if (file_exists($dbPath)) {
    unlink($dbPath); // каждый запуск пересоздаёт базу с нуля
}

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec('
    CREATE TABLE parcels (
        id            TEXT PRIMARY KEY,
        ku            TEXT NOT NULL,
        label         TEXT,
        area          INTEGER,
        national_ref  TEXT,
        min_lng       REAL NOT NULL,
        min_lat       REAL NOT NULL,
        max_lng       REAL NOT NULL,
        max_lat       REAL NOT NULL,
        geometry      TEXT NOT NULL
    )
');
$pdo->exec('CREATE INDEX idx_parcels_bbox ON parcels (min_lng, max_lng, min_lat, max_lat)');

$insert = $pdo->prepare('INSERT INTO parcels VALUES (?,?,?,?,?,?,?,?,?,?)');

// Рекурсивно обходит координаты любой геометрии и расширяет bbox
function extendBbox(array $coords, array &$b): void
{
    if (is_numeric($coords[0])) {
        [$x, $y] = $coords;
        $b = [min($b[0], $x), min($b[1], $y), max($b[2], $x), max($b[3], $y)];
        return;
    }
    foreach ($coords as $c) {
        extendBbox($c, $b);
    }
}

foreach (glob($dataDir . '/*.geojson') as $file) {
    $ku  = basename($file, '.geojson');
    $geo = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

    $pdo->beginTransaction();
    foreach ($geo['features'] as $f) {
        $p = $f['properties'];
        $b = [INF, INF, -INF, -INF];
        extendBbox($f['geometry']['coordinates'], $b);

        $insert->execute([
            $p['gml_id'],
            $ku,
            $p['label'],
            $p['areaValue'],
            $p['nationalCadastralReference'],
            $b[0], $b[1], $b[2], $b[3],
            json_encode($f['geometry'], JSON_THROW_ON_ERROR),
        ]);
    }
    $pdo->commit();

    echo sprintf("%s: %d parcel\n", $ku, count($geo['features']));
}

echo "Připraveno: " . $dbPath . "\n";