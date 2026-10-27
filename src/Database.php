<?php
declare(strict_types=1);

namespace Viagem;

use PDO;

final class Database
{
    public static function connect(): PDO
    {
        $path = dirname(__DIR__) . '/data/parcels.sqlite';

        if (!is_file($path)) {
            throw new \RuntimeException('Databáze není připravena, spusťte scripts/import.php');
        }

        return new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    }
}