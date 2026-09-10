#!/usr/bin/env php
<?php

/**
 * Runner de migrations e seeders.
 *
 * Uso:
 *   php database/migrate.php           — aplica migrations pendentes
 *   php database/migrate.php --seed    — aplica migrations + seeders
 *   php database/migrate.php --fresh   — dropa tudo e recria (APENAS DEV)
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));

require ROOT_PATH . '/vendor/autoload.php';

use Dotenv\Dotenv;

if (file_exists(ROOT_PATH . '/.env')) {
    $dotenv = Dotenv::createImmutable(ROOT_PATH);
    $dotenv->load();
}

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    $_ENV['DB_HOST'],
    $_ENV['DB_PORT'] ?? '3306',
    $_ENV['DB_NAME'],
);

try {
    $pdo = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "Conexão falhou: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

// Tabela de controle de migrations
$pdo->exec(<<<SQL
    CREATE TABLE IF NOT EXISTS migrations (
        id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
        arquivo    VARCHAR(200) NOT NULL,
        aplicado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_migration (arquivo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);

$args  = array_slice($argv, 1);
$seed  = in_array('--seed', $args, true);
$fresh = in_array('--fresh', $args, true);

if ($fresh) {
    if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
        fwrite(STDERR, "ERRO: --fresh não pode ser usado em produção.\n");
        exit(1);
    }
    echo "⚠  Dropping todas as tabelas...\n";
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $t) {
        $pdo->exec("DROP TABLE IF EXISTS `{$t}`");
        echo "   Dropped: {$t}\n";
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    // Recriar tabela de controle após drop
    $pdo->exec(<<<SQL
        CREATE TABLE migrations (
            id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
            arquivo    VARCHAR(200) NOT NULL,
            aplicado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_migration (arquivo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    SQL);
}

// Migrations
$applied = $pdo->query("SELECT arquivo FROM migrations")->fetchAll(PDO::FETCH_COLUMN);
$applied = array_flip($applied);

$dir   = ROOT_PATH . '/database/migrations';
$files = glob($dir . '/*.sql');
sort($files);

foreach ($files as $file) {
    $name = basename($file);
    if (isset($applied[$name])) {
        echo "  SKIP   {$name}\n";
        continue;
    }

    $sql = file_get_contents($file);
    try {
        $pdo->exec($sql);
        $pdo->prepare("INSERT INTO migrations (arquivo) VALUES (?)")->execute([$name]);
        echo "  APPLY  {$name}\n";
    } catch (PDOException $e) {
        fwrite(STDERR, "ERRO em {$name}: " . $e->getMessage() . PHP_EOL);
        exit(1);
    }
}

// Seeders
if ($seed) {
    $sdir  = ROOT_PATH . '/database/seeders';
    $seeds = glob($sdir . '/*.sql');
    sort($seeds);
    echo "\nSeeders:\n";
    foreach ($seeds as $file) {
        $name = basename($file);
        $sql  = file_get_contents($file);
        try {
            $pdo->exec($sql);
            echo "  SEED   {$name}\n";
        } catch (PDOException $e) {
            fwrite(STDERR, "ERRO em {$name}: " . $e->getMessage() . PHP_EOL);
            exit(1);
        }
    }
}

echo "\nMigrations concluídas.\n";
