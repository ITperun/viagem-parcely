<?php
declare(strict_types=1);

namespace Viagem;

// Единая точка для JSON-ответов и ошибок
final class Response
{
    public static function error(int $code, string $message): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
}