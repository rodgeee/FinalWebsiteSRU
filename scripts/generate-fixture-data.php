<?php

/**
 * Export MySQL rows into src/DataFixtures/AppFixtures.php (embedded PHP arrays).
 *
 * Usage (project root):
 *   php scripts/generate-fixture-data.php
 *
 * Optional env: DATABASE_URL or MYSQL_* from .env (loaded below).
 */

declare(strict_types=1);

$root = dirname(__DIR__);
if (is_readable($root.'/.env')) {
    foreach (file($root.'/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, " \t\"'");
        if ($name !== '' && getenv($name) === false) {
            putenv($name.'='.$value);
            $_ENV[$name] = $value;
        }
    }
}

$databaseUrl = getenv('DATABASE_URL') ?: '';
if ($databaseUrl === '') {
    fwrite(STDERR, "DATABASE_URL is not set.\n");
    exit(1);
}

$parts = parse_url($databaseUrl);
if ($parts === false || !isset($parts['host'])) {
    fwrite(STDERR, "Invalid DATABASE_URL.\n");
    exit(1);
}

$dbName = ltrim((string) ($parts['path'] ?? ''), '/');
if (str_contains($dbName, '?')) {
    $dbName = strstr($dbName, '?', true) ?: $dbName;
}

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
    $parts['host'],
    $parts['port'] ?? 3306,
    $dbName,
);

$pdo = new PDO(
    $dsn,
    (string) ($parts['user'] ?? 'root'),
    (string) ($parts['pass'] ?? ''),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);

$tables = [
    'adminuser',
    'staff',
    'customer',
    'customer_address',
    'products',
    'stocks',
    'services',
    'orders',
    'orders_products',
    'activity_log',
];

$data = [
    'generated_at' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
    'tables' => [],
];

foreach ($tables as $table) {
    $stmt = $pdo->query(sprintf('SELECT * FROM `%s`', $table));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        foreach ($row as $key => $value) {
            if (is_string($value) && in_array($key, ['images', 'roles', 'changes'], true)) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $row[$key] = $decoded;
                }
            }
        }
    }
    unset($row);
    $data['tables'][$table] = $rows;
    echo sprintf("%s: %d row(s)\n", $table, count($rows));
}

$outDir = $root.'/src/DataFixtures';
if (!is_dir($outDir)) {
    mkdir($outDir, 0775, true);
}

require __DIR__.'/build-app-fixtures-class.php';
build_app_fixtures_file($root, var_export($data['tables'], true));
@unlink($outDir.'/fixture-data.json');
@unlink($outDir.'/AppFixtureData.php');
