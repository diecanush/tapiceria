<?php

declare(strict_types=1);

function app_title(): string
{
    return 'Tapicería MVP';
}

function data_dir(): string
{
    return __DIR__ . '/../data';
}

function data_file(string $name): string
{
    return data_dir() . '/' . $name . '.json';
}

function next_id(array $rows): int
{
    $ids = array_map(static function (array $row): int {
        return (int) ($row['id'] ?? 0);
    }, $rows);
    $max = $ids === [] ? 0 : max($ids);
    return $max + 1;
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function app_url(string $path = ''): string
{
    return $path === '' ? '' : '/' . ltrim($path, '/');
}

function redirect_with_message(string $path, string $message): never
{
    header('Location: ' . app_url($path) . '?msg=' . rawurlencode($message));
    exit;
}

function money(float $value): string
{
    return '$' . number_format($value, 2, ',', '.');
}
