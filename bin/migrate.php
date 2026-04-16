<?php

declare(strict_types=1);

use App\Repositories\Database;

require_once __DIR__ . '/../bootstrap/autoload.php';
require_once __DIR__ . '/../app/Core/helpers.php';

$envPath = __DIR__ . '/../.env';
if (is_file($envPath)) {
    $envVars = parse_ini_file($envPath, false, INI_SCANNER_TYPED);
    if (is_array($envVars)) {
        foreach ($envVars as $key => $value) {
            $stringValue = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
            $_ENV[$key] = $stringValue;
            $_SERVER[$key] = $stringValue;
            putenv(sprintf('%s=%s', $key, $stringValue));
        }
    }
}

$databaseConfig = require __DIR__ . '/../config/database.php';
$db = new Database($databaseConfig);
$pdo = $db->connection();

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        id BIGSERIAL PRIMARY KEY,
        migration VARCHAR(255) NOT NULL UNIQUE,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    )'
);

$migrationDir = __DIR__ . '/../database/migrations';
$files = glob($migrationDir . '/*.sql');
sort($files);

foreach ($files as $file) {
    $migration = basename($file);
    $stmt = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE migration = :migration LIMIT 1');
    $stmt->execute(['migration' => $migration]);
    if ($stmt->fetch()) {
        echo "[skip] {$migration}\n";
        continue;
    }

    echo "[run] {$migration}\n";
    $sql = file_get_contents($file);
    if (!is_string($sql)) {
        throw new RuntimeException('Unable to read migration file: ' . $migration);
    }

    $pdo->beginTransaction();
    try {
        $pdo->exec($sql);
        $insert = $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (:migration)');
        $insert->execute(['migration' => $migration]);
        $pdo->commit();
        echo "[ok] {$migration}\n";
    } catch (Throwable $e) {
        $pdo->rollBack();
        echo "[fail] {$migration} - {$e->getMessage()}\n";
        exit(1);
    }
}

echo "Migrations finished.\n";

